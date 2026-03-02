<?php
declare(strict_types=1);

/**
 * Normalize imported VTEX catalog data in WooCommerce.
 *
 * Usage:
 * /Applications/MAMP/bin/php/php8.2.0/bin/php scripts/normalize_vtex_import.php
 */

define('VTEX_BASE_URL', 'https://loja.absglobal.com');
define('VTEX_PAGE_SIZE', 30);

$root = dirname(__DIR__);
require_once $root . '/wp-load.php';

if (!function_exists('wc_get_product')) {
    fwrite(STDERR, "WooCommerce is not active.\n");
    exit(1);
}

set_time_limit(0);

function fetchJsonNormalized(string $url): ?array
{
    $tries = 3;
    for ($i = 1; $i <= $tries; $i++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ABSGlobal-Normalizer/1.0',
            ],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body !== false && $status >= 200 && $status < 300) {
            $json = json_decode($body, true);
            if (is_array($json)) {
                return $json;
            }
        }
        sleep(1);
    }

    return null;
}

function normalizeText(?string $value): string
{
    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim((string) $value);
}

function smartTitleToken(string $token, bool $isFirstWord): string
{
    static $lowerWords = [
        'a', 'as', 'o', 'os', 'de', 'da', 'das', 'do', 'dos',
        'e', 'em', 'no', 'na', 'nos', 'nas', 'por', 'para', 'com', 'sem',
    ];
    static $upperWords = ['ABS', 'ET', 'SX', 'IA', 'WTA', 'DSP', 'MVE', 'XC', 'SC', 'TW'];

    $token = trim($token);
    if ($token === '') {
        return '';
    }

    if (preg_match('/\d/u', $token)) {
        return $token;
    }

    $parts = explode('-', $token);
    if (count($parts) > 1) {
        $out = [];
        foreach ($parts as $i => $part) {
            $out[] = smartTitleToken($part, $isFirstWord && $i === 0);
        }
        return implode('-', $out);
    }

    $upper = mb_strtoupper($token, 'UTF-8');
    if (in_array($upper, $upperWords, true)) {
        return $upper;
    }

    $lower = mb_strtolower($token, 'UTF-8');
    if (!$isFirstWord && in_array($lower, $lowerWords, true)) {
        return $lower;
    }

    return mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
}

function normalizeProductName(string $name): string
{
    $name = normalizeText($name);
    $name = str_replace(['=', '–', '—'], ' - ', $name);
    $name = preg_replace('/\s+-\s+/u', ' - ', $name);
    if (!is_string($name)) {
        $name = normalizeText($name);
    }

    // Split glued semantic blocks, e.g. XAPETUBA-SEMEN-CONVENCIONAL.
    do {
        $previous = $name;
        $name = preg_replace('/(\p{L}{3,})-(\p{L}{3,})/u', '$1 - $2', $name);
        if (!is_string($name)) {
            $name = $previous;
            break;
        }
    } while ($name !== $previous);
    $name = preg_replace('/\b([A-Za-z]{2,})-(Semen|Convencional|Sexcel|Leite|Corte|Girolando|Holandes|Holandês|Femea|Fêmea)\b/u', '$1 - $2', $name);
    if (!is_string($name)) {
        $name = normalizeText($name);
    }

    $name = preg_replace('/\s+/u', ' ', $name);
    if (!is_string($name)) {
        $name = normalizeText($name);
    }
    $name = trim((string) $name, " \t\n\r\0\x0B-");

    $words = preg_split('/\s+/u', $name) ?: [];
    $out = [];
    foreach ($words as $idx => $word) {
        $out[] = smartTitleToken($word, $idx === 0);
    }

    return trim(implode(' ', $out));
}

function flattenCategoryTree(array $nodes, ?int $parentId, array &$map): void
{
    foreach ($nodes as $node) {
        $id = isset($node['id']) ? (int) $node['id'] : 0;
        if ($id <= 0) {
            continue;
        }

        $name = normalizeText((string) ($node['name'] ?? ''));
        $title = normalizeText((string) ($node['Title'] ?? ''));
        if ($name === '' || $name === '.') {
            $name = $title !== '' ? $title : 'Categoria ' . $id;
        }

        $map[$id] = [
            'id' => $id,
            'name' => $name,
            'url' => (string) ($node['url'] ?? ''),
            'parent_vtex_id' => $parentId,
        ];

        $children = $node['children'] ?? [];
        if (is_array($children) && $children) {
            flattenCategoryTree($children, $id, $map);
        }
    }
}

function findTermByVtexCategoryId(int $vtexCategoryId): ?WP_Term
{
    $terms = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'meta_key' => '_vtex_category_id',
        'meta_value' => (string) $vtexCategoryId,
        'number' => 1,
    ]);
    if (is_wp_error($terms) || !$terms) {
        return null;
    }
    return $terms[0];
}

function categorySlugFromNode(array $node): string
{
    $path = parse_url((string) ($node['url'] ?? ''), PHP_URL_PATH);
    if (is_string($path) && $path !== '') {
        $parts = array_values(array_filter(explode('/', trim($path, '/'))));
        if ($parts) {
            return sanitize_title(end($parts));
        }
    }
    return sanitize_title((string) ($node['name'] ?? 'cat-' . $node['id']));
}

function ensureCategoryByVtexNode(array $node, ?int $parentTermId): ?int
{
    $vtexId = (int) ($node['id'] ?? 0);
    if ($vtexId <= 0) {
        return null;
    }

    $existing = findTermByVtexCategoryId($vtexId);
    $name = normalizeText((string) ($node['name'] ?? 'Categoria ' . $vtexId));
    $slug = categorySlugFromNode($node);
    $parent = (int) ($parentTermId ?? 0);

    if ($existing) {
        $updated = wp_update_term((int) $existing->term_id, 'product_cat', [
            'name' => $name,
            'slug' => $slug,
            'parent' => $parent,
        ]);
        if (is_wp_error($updated)) {
            wp_update_term((int) $existing->term_id, 'product_cat', [
                'name' => $name,
                'parent' => $parent,
            ]);
        }
        return (int) $existing->term_id;
    }

    $bySlug = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'slug' => $slug,
        'parent' => $parent,
        'number' => 1,
    ]);
    if (!is_wp_error($bySlug) && $bySlug) {
        $termId = (int) $bySlug[0]->term_id;
        update_term_meta($termId, '_vtex_category_id', (string) $vtexId);
        update_term_meta($termId, '_vtex_category_url', (string) ($node['url'] ?? ''));
        wp_update_term($termId, 'product_cat', ['name' => $name, 'parent' => $parent]);
        return $termId;
    }

    $byName = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
        'name' => $name,
        'parent' => $parent,
        'number' => 1,
    ]);
    if (!is_wp_error($byName) && $byName) {
        $termId = (int) $byName[0]->term_id;
        update_term_meta($termId, '_vtex_category_id', (string) $vtexId);
        update_term_meta($termId, '_vtex_category_url', (string) ($node['url'] ?? ''));
        return $termId;
    }

    $insert = wp_insert_term($name, 'product_cat', [
        'slug' => $slug,
        'parent' => $parent,
    ]);

    if (is_wp_error($insert)) {
        $fallbackSlug = $slug . '-' . $vtexId;
        $insert = wp_insert_term($name, 'product_cat', [
            'slug' => $fallbackSlug,
            'parent' => $parent,
        ]);
        if (is_wp_error($insert)) {
            fwrite(STDERR, "Failed term {$name} ({$vtexId})\n");
            return null;
        }
    }

    $termId = (int) $insert['term_id'];
    update_term_meta($termId, '_vtex_category_id', (string) $vtexId);
    update_term_meta($termId, '_vtex_category_url', (string) ($node['url'] ?? ''));
    return $termId;
}

function getProductIdByVtexId(string $vtexProductId): ?int
{
    $ids = get_posts([
        'post_type' => 'product',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => '_vtex_product_id',
        'meta_value' => $vtexProductId,
    ]);
    if (!$ids) {
        return null;
    }
    return (int) $ids[0];
}

echo "Starting normalization...\n";

$tree = fetchJsonNormalized(VTEX_BASE_URL . '/api/catalog_system/pub/category/tree/10');
if (!$tree) {
    fwrite(STDERR, "Could not fetch VTEX category tree.\n");
    exit(1);
}

$categoryMap = [];
flattenCategoryTree($tree, null, $categoryMap);

$termByVtexId = [];
$orderedIds = array_keys($categoryMap);
usort($orderedIds, function (int $a, int $b) use ($categoryMap): int {
    $pa = $categoryMap[$a]['parent_vtex_id'] ?? null;
    $pb = $categoryMap[$b]['parent_vtex_id'] ?? null;
    if ($pa === null && $pb !== null) {
        return -1;
    }
    if ($pa !== null && $pb === null) {
        return 1;
    }
    return $a <=> $b;
});

foreach ($orderedIds as $vtexId) {
    $node = $categoryMap[$vtexId];
    $parentVtexId = $node['parent_vtex_id'];
    $parentTermId = null;
    if ($parentVtexId !== null && isset($termByVtexId[$parentVtexId])) {
        $parentTermId = $termByVtexId[$parentVtexId];
    }
    $termId = ensureCategoryByVtexNode($node, $parentTermId);
    if ($termId) {
        $termByVtexId[$vtexId] = $termId;
    }
}

$updatedProducts = 0;
$offset = 0;

while (true) {
    $to = $offset + VTEX_PAGE_SIZE - 1;
    $url = VTEX_BASE_URL . "/api/catalog_system/pub/products/search?_from={$offset}&_to={$to}";
    $batch = fetchJsonNormalized($url);
    if (!is_array($batch) || count($batch) === 0) {
        break;
    }

    foreach ($batch as $row) {
        $vtexId = (string) ($row['productId'] ?? '');
        if ($vtexId === '') {
            continue;
        }
        $wpProductId = getProductIdByVtexId($vtexId);
        if (!$wpProductId) {
            continue;
        }

        $product = wc_get_product($wpProductId);
        if (!$product instanceof WC_Product) {
            continue;
        }

        $name = normalizeProductName((string) ($row['productName'] ?? $product->get_name()));
        $slug = sanitize_title((string) ($row['linkText'] ?? $name));
        $description = (string) ($row['description'] ?? '');
        $short = normalizeText((string) ($row['metaTagDescription'] ?? ''));

        $product->set_name($name);
        if ($slug !== '') {
            $product->set_slug($slug);
        }
        $product->set_description($description);
        $product->set_short_description($short);
        $product->save();

        $paths = (array) ($row['categoriesIds'] ?? []);
        $leafTerms = [];
        foreach ($paths as $path) {
            if (!is_string($path) || trim($path) === '') {
                continue;
            }
            $ids = array_values(array_filter(array_map('intval', explode('/', trim($path, '/')))));
            if (!$ids) {
                continue;
            }
            $leaf = end($ids);
            if ($leaf && isset($termByVtexId[$leaf])) {
                $leafTerms[] = $termByVtexId[$leaf];
            }
        }

        if ($leafTerms) {
            wp_set_object_terms($wpProductId, array_values(array_unique($leafTerms)), 'product_cat', false);
        }

        $updatedProducts++;
    }

    $offset += VTEX_PAGE_SIZE;
}

// Final pass over all published products (including non-VTEX leftovers).
$allPublishedProducts = get_posts([
    'post_type' => 'product',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
]);
foreach ($allPublishedProducts as $productId) {
    $currentTitle = (string) get_post_field('post_title', (int) $productId);
    $normalizedTitle = normalizeProductName($currentTitle);
    if ($normalizedTitle !== '' && $normalizedTitle !== $currentTitle) {
        wp_update_post([
            'ID' => (int) $productId,
            'post_title' => $normalizedTitle,
        ]);
    }
}

// Clean default content from fresh installation (keep Woo pages).
$defaultPosts = get_posts([
    'post_type' => 'post',
    'post_status' => ['publish', 'draft', 'auto-draft'],
    'posts_per_page' => 50,
    'fields' => 'ids',
]);
foreach ($defaultPosts as $postId) {
    wp_trash_post((int) $postId);
}

$defaultPages = get_posts([
    'post_type' => 'page',
    'post_status' => ['publish', 'draft'],
    'posts_per_page' => 100,
    'fields' => 'ids',
    'meta_query' => [],
]);
foreach ($defaultPages as $pageId) {
    $slug = get_post_field('post_name', (int) $pageId);
    if (in_array($slug, ['shop', 'cart', 'checkout', 'my-account'], true)) {
        continue;
    }
    wp_trash_post((int) $pageId);
}

// Remove empty malformed categories produced by path splitting.
$maybeDelete = get_terms([
    'taxonomy' => 'product_cat',
    'hide_empty' => false,
]);
if (!is_wp_error($maybeDelete)) {
    foreach ($maybeDelete as $term) {
        $name = normalizeText($term->name);
        $hasVtex = get_term_meta((int) $term->term_id, '_vtex_category_id', true);
        $count = (int) $term->count;
        if ($hasVtex !== '') {
            continue;
        }
        if ($count > 0) {
            continue;
        }
        if ($name === '.' || preg_match('/^\d+$/', $name)) {
            wp_delete_term((int) $term->term_id, 'product_cat');
        }
    }
}

echo "Normalization finished.\n";
echo "Products normalized: {$updatedProducts}\n";
echo "Categories mapped: " . count($termByVtexId) . "\n";
