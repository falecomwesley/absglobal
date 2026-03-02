#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script via CLI.\n");
    exit(1);
}

$wpLoadPath = $argv[1] ?? '/var/www/html/absloja/wp-load.php';
if (!file_exists($wpLoadPath)) {
    fwrite(STDERR, "wp-load.php not found: {$wpLoadPath}\n");
    exit(1);
}

require_once $wpLoadPath;

if (!function_exists('wp_insert_post')) {
    fwrite(STDERR, "WordPress bootstrap failed.\n");
    exit(1);
}

function fetch_html(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ABS-SyncBot/1.0)',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($body) || $body === '' || $code < 200 || $code >= 400) {
        return null;
    }

    return $body;
}

function extract_main_html(string $html): string
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    // Remove noisy nodes that pollute imported content.
    foreach (['//script', '//style', '//noscript', '//svg'] as $query) {
        $nodes = $xpath->query($query);
        if (!$nodes) {
            continue;
        }
        foreach ($nodes as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    $candidates = [
        '//main',
        '//article',
        '//*[contains(@class,"institutional")]',
        '//*[contains(@class,"content")]',
        '//*[contains(@id,"content")]',
        '//body',
    ];

    $bestHtml = '';
    $bestLen = 0;
    foreach ($candidates as $query) {
        $nodes = $xpath->query($query);
        if (!$nodes || $nodes->length === 0) {
            continue;
        }
        foreach ($nodes as $node) {
            $textLen = mb_strlen(trim((string) $node->textContent));
            if ($textLen < 120 || $textLen <= $bestLen) {
                continue;
            }
            $bestLen = $textLen;
            $bestHtml = inner_html($node);
        }
    }

    if ($bestHtml === '') {
        return '';
    }

    // Keep only common content tags to avoid importing VTEX wrappers/scripts.
    $clean = wp_kses(
        $bestHtml,
        [
            'p' => [],
            'a' => ['href' => true, 'title' => true, 'target' => true, 'rel' => true],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'h5' => [],
            'h6' => [],
            'strong' => [],
            'em' => [],
            'br' => [],
            'blockquote' => [],
            'table' => [],
            'thead' => [],
            'tbody' => [],
            'tr' => [],
            'td' => [],
            'th' => [],
            'img' => ['src' => true, 'alt' => true, 'title' => true],
            'hr' => [],
            'div' => ['class' => true],
            'span' => ['class' => true],
        ]
    );

    return trim($clean);
}

function inner_html(DOMNode $node): string
{
    $doc = $node->ownerDocument;
    if (!$doc) {
        return '';
    }
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $doc->saveHTML($child);
    }
    return $html;
}

function upsert_page(string $title, string $slug, string $content): int
{
    $existing = get_page_by_path($slug, OBJECT, 'page');
    $postarr = [
        'post_title' => $title,
        'post_name' => $slug,
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_content' => $content,
    ];

    if ($existing) {
        $postarr['ID'] = $existing->ID;
        return (int) wp_update_post($postarr, true);
    }
    return (int) wp_insert_post($postarr, true);
}

$targets = [
    [
        'title' => 'Meus Pedidos',
        'slug' => 'meus-pedidos',
        'type' => 'external',
        'url' => '/my-account/orders/',
    ],
    [
        'title' => 'Manual de uso do botijão',
        'slug' => 'manual-uso-botijao',
        'type' => 'external',
        'url' => 'https://www.absglobal.com/br/wp-content/uploads/sites/16/2022/05/Manual-versao-final-PDF-para-site.pdf',
    ],
    [
        'title' => 'Política de Troca e Devolução',
        'slug' => 'politica-troca-devolucao',
        'type' => 'scrape',
        'url' => 'https://loja.absglobal.com/Institucional/politica-troca-devolucao',
    ],
    [
        'title' => 'Solicitar Troca/Devolução',
        'slug' => 'solicitar-troca-devolucao',
        'type' => 'external',
        'url' => 'https://pt.surveymonkey.com/r/troca-devolucao',
    ],
    [
        'title' => 'Política de entrega',
        'slug' => 'politica-de-entrega',
        'type' => 'external',
        'url' => 'https://www.absglobal.com/br/politica-de-privacidade/',
    ],
    [
        'title' => 'Política de privacidade',
        'slug' => 'politica-de-privacidade',
        'type' => 'external',
        'url' => 'https://www.absglobal.com/br/politica-de-privacidade/',
    ],
    [
        'title' => 'Sobre Nós',
        'slug' => 'sobre-nos',
        'type' => 'scrape',
        'url' => 'https://lojaabsbrasil.myvtex.com/institucional/quem-somos',
    ],
    [
        'title' => 'Trabalhe Conosco',
        'slug' => 'trabalhe-conosco',
        'type' => 'external',
        'url' => 'https://www.absglobal.com/br/trabalhe-conosco/',
    ],
    [
        'title' => 'Material Merchandising',
        'slug' => 'material-merchandising',
        'type' => 'external',
        'url' => 'https://absloja.jjconsulting.com.br/categoria-produto/material-merchandising/',
    ],
    [
        'title' => 'Catálogo',
        'slug' => 'catalogo',
        'type' => 'external',
        'url' => 'https://loja.absglobal.com/material-merchandising/catalogo',
    ],
    [
        'title' => 'App ABS',
        'slug' => 'app-abs',
        'type' => 'external',
        'url' => 'https://abs.link/app/',
    ],
    [
        'title' => 'ABS Monitor',
        'slug' => 'abs-monitor',
        'type' => 'external',
        'url' => 'https://abstechservices.com/absmonitor/',
    ],
    [
        'title' => 'Sync',
        'slug' => 'sync',
        'type' => 'external',
        'url' => 'https://sync.abspecplan.com.br/',
    ],
    [
        'title' => 'Política de Cookies',
        'slug' => 'politica-de-cookies',
        'type' => 'static',
        'content' => '<p>Usamos cookies: Armazenamos dados temporariamente para melhorar a sua experiência de navegação e recomendar conteúdos de seu interesse. Ao navegar pela loja ABS, você concorda com tal monitoramento.</p>',
    ],
];

$baseInfo = "<p>Conteúdo sincronizado automaticamente a partir do portal oficial da ABS.</p>";
$results = [];

foreach ($targets as $target) {
    $title = $target['title'];
    $slug = $target['slug'];
    $content = '';

    if ($target['type'] === 'scrape') {
        $html = fetch_html($target['url']);
        $main = $html ? extract_main_html($html) : '';
        if ($main === '' || mb_strlen(strip_tags($main)) < 120) {
            $main = "<p>Não foi possível importar o corpo completo automaticamente.</p>";
        }
        $content = $baseInfo . $main . '<p><a href="' . esc_url($target['url']) . '" target="_blank" rel="noopener">Abrir fonte oficial</a></p>';
    } elseif ($target['type'] === 'external') {
        $href = $target['url'];
        if (str_starts_with($href, '/')) {
            $href = home_url($href);
        }
        $content = $baseInfo . '<p><a href="' . esc_url($href) . '" target="_blank" rel="noopener">Acessar ' . esc_html($title) . '</a></p>';
    } else {
        $content = (string) $target['content'];
    }

    $postId = upsert_page($title, $slug, $content);
    $ok = !is_wp_error($postId) && $postId > 0;
    $results[] = [$title, $slug, $ok ? 'ok' : 'error', (string) $postId];
}

echo "Institutional sync results\n";
echo "==========================\n";
foreach ($results as [$title, $slug, $status, $id]) {
    echo "{$status}\t{$id}\t{$slug}\t{$title}\n";
}

