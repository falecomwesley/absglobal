<?php
declare(strict_types=1);

/**
 * VTEX -> WooCommerce importer.
 *
 * Usage:
 * /Applications/MAMP/bin/php/php8.2.0/bin/php scripts/import_vtex_to_woocommerce.php
 */

define('VTEX_BASE_URL', 'https://loja.absglobal.com');
define('VTEX_PAGE_SIZE', 20);

$root = dirname(__DIR__);
require_once $root . '/wp-load.php';

if (!function_exists('wc_get_product')) {
    fwrite(STDERR, "WooCommerce is not active.\n");
    exit(1);
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

set_time_limit(0);

function fetchJson(string $url): ?array
{
    $attempts = 3;
    for ($i = 1; $i <= $attempts; $i++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ABSGlobal-Migrator/1.0',
            ],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body !== false && $status >= 200 && $status < 300) {
            $json = json_decode($body, true);
            if (is_array($json)) {
                return $json;
            }
        }

        if ($i < $attempts) {
            sleep(1);
            continue;
        }

        fwrite(STDERR, "Failed fetching {$url} (status {$status}) {$err}\n");
        return null;
    }

    return null;
}

function slugFromPath(string $segment): string
{
    $segment = remove_accents($segment);
    $segment = strtolower(trim($segment));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $segment);
    $slug = trim((string) $slug, '-');
    return $slug !== '' ? $slug : 'categoria';
}

function ensureCategoryPath(string $categoryPath): ?int
{
    $parts = array_values(array_filter(array_map('trim', explode('/', $categoryPath))));
    if (!$parts) {
        return null;
    }

    $parent = 0;
    $lastId = null;

    foreach ($parts as $part) {
        $slug = slugFromPath($part);
        $term = get_term_by('slug', $slug, 'product_cat');

        if (!$term || is_wp_error($term)) {
            $inserted = wp_insert_term($part, 'product_cat', [
                'slug' => $slug,
                'parent' => $parent,
            ]);

            if (is_wp_error($inserted)) {
                $existing = get_term_by('name', $part, 'product_cat');
                if ($existing && !is_wp_error($existing)) {
                    $lastId = (int) $existing->term_id;
                    $parent = $lastId;
                    continue;
                }
                fwrite(STDERR, "Category creation failed: {$part}\n");
                return $lastId;
            }

            $lastId = (int) $inserted['term_id'];
        } else {
            $lastId = (int) $term->term_id;
            if ((int) $term->parent !== $parent) {
                wp_update_term($lastId, 'product_cat', ['parent' => $parent]);
            }
        }

        $parent = $lastId;
    }

    return $lastId;
}

function findOrCreateProduct(string $productId, string $slug, string $name): WC_Product_Simple
{
    $existing = get_posts([
        'post_type' => 'product',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'meta_key' => '_vtex_product_id',
        'meta_value' => $productId,
        'fields' => 'ids',
    ]);

    if (!$existing) {
        $bySlug = get_page_by_path($slug, OBJECT, 'product');
        if ($bySlug) {
            $existing = [(int) $bySlug->ID];
        }
    }

    if ($existing) {
        $product = wc_get_product((int) $existing[0]);
        if ($product instanceof WC_Product_Simple) {
            return $product;
        }
    }

    $product = new WC_Product_Simple();
    $product->set_name($name);
    $product->set_slug($slug);
    $product->set_status('publish');
    $product->save();

    return $product;
}

function setProductImageIfNeeded(int $productId, array $images): void
{
    if (!$images) {
        return;
    }

    $thumbId = get_post_thumbnail_id($productId);
    $galleryIds = get_post_meta($productId, '_product_image_gallery', true);
    if ($thumbId && !empty($galleryIds)) {
        return;
    }

    $downloaded = [];
    foreach ($images as $img) {
        $url = $img['imageUrl'] ?? '';
        if (!$url || !is_string($url)) {
            continue;
        }

        $attachmentId = media_sideload_image($url, $productId, null, 'id');
        if (is_wp_error($attachmentId)) {
            continue;
        }
        $downloaded[] = (int) $attachmentId;
    }

    if (!$downloaded) {
        return;
    }

    set_post_thumbnail($productId, $downloaded[0]);
    if (count($downloaded) > 1) {
        $gallery = implode(',', array_slice($downloaded, 1));
        update_post_meta($productId, '_product_image_gallery', $gallery);
    }
}

function normalizeSlug(array $product): string
{
    $linkText = $product['linkText'] ?? '';
    if (is_string($linkText) && $linkText !== '') {
        return sanitize_title($linkText);
    }

    $link = $product['link'] ?? '';
    if (is_string($link) && $link !== '') {
        $parts = parse_url($link);
        if (!empty($parts['path'])) {
            $path = trim((string) $parts['path'], '/');
            $path = preg_replace('#/p$#', '', $path);
            if ($path !== '') {
                return sanitize_title($path);
            }
        }
    }

    $name = $product['productName'] ?? 'produto';
    return sanitize_title((string) $name);
}

echo "Starting VTEX import...\n";

$offset = 0;
$imported = 0;
$updated = 0;
$failed = 0;

while (true) {
    $to = $offset + VTEX_PAGE_SIZE - 1;
    $url = VTEX_BASE_URL . "/api/catalog_system/pub/products/search?_from={$offset}&_to={$to}";
    $batch = fetchJson($url);

    if (!is_array($batch) || count($batch) === 0) {
        break;
    }

    foreach ($batch as $row) {
        try {
            $productId = (string) ($row['productId'] ?? '');
            $name = (string) ($row['productName'] ?? '');
            if ($productId === '' || $name === '') {
                $failed++;
                continue;
            }

            $slug = normalizeSlug($row);
            $description = (string) ($row['description'] ?? '');
            $item = $row['items'][0] ?? [];
            $offer = $item['sellers'][0]['commertialOffer'] ?? [];

            $price = isset($offer['Price']) ? (float) $offer['Price'] : 0.0;
            $listPrice = isset($offer['ListPrice']) ? (float) $offer['ListPrice'] : $price;
            $available = !empty($offer['AvailableQuantity']) && ((int) $offer['AvailableQuantity'] > 0);

            $existing = get_posts([
                'post_type' => 'product',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'meta_key' => '_vtex_product_id',
                'meta_value' => $productId,
                'fields' => 'ids',
            ]);
            $wasExisting = !empty($existing);

            $product = findOrCreateProduct($productId, $slug, $name);
            $product->set_name($name);
            $product->set_slug($slug);
            $product->set_description($description);
            $product->set_short_description((string) ($row['metaTagDescription'] ?? ''));
            $product->set_regular_price(number_format($listPrice, 2, '.', ''));
            $product->set_price(number_format($price, 2, '.', ''));
            $product->set_manage_stock(false);
            $product->set_stock_status($available ? 'instock' : 'outofstock');

            $sku = (string) ($item['itemId'] ?? '');
            if ($sku !== '') {
                $product->set_sku($sku);
            }

            $product->save();
            $wpProductId = $product->get_id();

            update_post_meta($wpProductId, '_vtex_product_id', $productId);
            update_post_meta($wpProductId, '_vtex_permalink', (string) ($row['link'] ?? ''));

            $termIds = [];
            foreach ((array) ($row['categories'] ?? []) as $categoryPath) {
                if (!is_string($categoryPath) || trim($categoryPath) === '') {
                    continue;
                }
                $termId = ensureCategoryPath($categoryPath);
                if ($termId) {
                    $termIds[] = $termId;
                }
            }
            if ($termIds) {
                wp_set_object_terms($wpProductId, array_values(array_unique($termIds)), 'product_cat');
            }

            $images = (array) ($item['images'] ?? []);
            setProductImageIfNeeded($wpProductId, $images);

            if ($wasExisting) {
                $updated++;
            } else {
                $imported++;
            }
            echo "OK {$productId} {$name}\n";
        } catch (Throwable $e) {
            $failed++;
            fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
        }
    }

    $offset += VTEX_PAGE_SIZE;
}

echo "Import finished.\n";
echo "Imported: {$imported}\n";
echo "Updated: {$updated}\n";
echo "Failed: {$failed}\n";

