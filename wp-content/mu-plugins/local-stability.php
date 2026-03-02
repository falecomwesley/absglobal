<?php
/**
 * Local stability tweaks for migration/admin reliability.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Nonce válido por 48h para evitar "Este link expirou" em ações longas.
add_filter('nonce_life', static function (): int {
    return 2 * DAY_IN_SECONDS;
});

// Reduz frequência do Heartbeat para aliviar admin local lento.
add_filter('heartbeat_settings', static function (array $settings): array {
    $settings['interval'] = 60;
    return $settings;
});
