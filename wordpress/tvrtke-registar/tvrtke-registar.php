<?php
/**
 * Plugin Name: TVRTKE – Poslovni registar
 * Description: Pretraživanje subjekata Sudskog registra preko centralnog API-ja tvrtke.com.hr.
 * Version: 0.1.0
 * Requires PHP: 8.2
 * Requires at least: 6.4
 * Text Domain: tvrtke-registar
 * License: MIT
 */

if (!defined('ABSPATH')) exit;

final class Tvrtke_Registar
{
    private const OPTION = 'tvrtke_registar_api_url';

    public static function init(): void
    {
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_menu', [self::class, 'menu']);
        add_shortcode('tvrtke_pretraga', [self::class, 'shortcode']);
    }

    public static function register_settings(): void
    {
        register_setting('tvrtke_registar', self::OPTION, [
            'type' => 'string',
            'default' => 'https://api.tvrtke.com.hr',
            'sanitize_callback' => [self::class, 'validate_url'],
        ]);
    }

    public static function validate_url($value): string
    {
        $url = untrailingslashit(esc_url_raw((string) $value, ['https']));
        if (!$url || wp_parse_url($url, PHP_URL_SCHEME) !== 'https' || wp_parse_url($url, PHP_URL_USER) !== null) {
            add_settings_error(self::OPTION, 'invalid', __('Potrebna je valjana HTTPS adresa API-ja.', 'tvrtke-registar'));
            return (string) get_option(self::OPTION, 'https://api.tvrtke.com.hr');
        }
        return $url;
    }

    public static function menu(): void
    {
        add_options_page('TVRTKE', 'TVRTKE', 'manage_options', 'tvrtke-registar', [self::class, 'settings_page']);
    }

    public static function settings_page(): void
    {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap"><h1>' . esc_html__('TVRTKE – postavke', 'tvrtke-registar') . '</h1>';
        echo '<p>' . esc_html__('Centralni API mora biti instaliran prije pretraživanja.', 'tvrtke-registar') . '</p>';
        echo '<form action="options.php" method="post">';
        settings_fields('tvrtke_registar');
        echo '<table class="form-table"><tr><th scope="row"><label for="tvrtke-api">' . esc_html__('HTTPS adresa API-ja', 'tvrtke-registar') . '</label></th><td>';
        echo '<input class="regular-text" id="tvrtke-api" name="' . esc_attr(self::OPTION) . '" value="' . esc_attr((string) get_option(self::OPTION, 'https://api.tvrtke.com.hr')) . '" type="url" required />';
        echo '</td></tr></table>';
        submit_button();
        echo '</form><p><code>[tvrtke_pretraga]</code></p></div>';
    }

    private static function fetch(string $search, int $page): array|WP_Error
    {
        $base = (string) get_option(self::OPTION, 'https://api.tvrtke.com.hr');
        $url = add_query_arg(['q' => $search, 'page' => $page, 'limit' => 20], $base . '/v1/subjects');
        $cacheKey = 'tvrtke_' . md5($url);
        $cached = get_transient($cacheKey);
        if (is_array($cached)) return $cached;
        $response = wp_safe_remote_get($url, ['timeout' => 8, 'redirection' => 0, 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($response)) return $response;
        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('tvrtke_remote', __('Servis trenutačno nije dostupan.', 'tvrtke-registar'));
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || !isset($body['items']) || !is_array($body['items'])) {
            return new WP_Error('tvrtke_invalid', __('Neispravan odgovor API-ja.', 'tvrtke-registar'));
        }
        set_transient($cacheKey, $body, 5 * MINUTE_IN_SECONDS);
        return $body;
    }

    public static function shortcode(): string
    {
        $q = isset($_GET['tvrtke_q']) && is_scalar($_GET['tvrtke_q']) ? sanitize_text_field(wp_unslash((string) $_GET['tvrtke_q'])) : '';
        $q = mb_substr(trim($q), 0, 100);
        $page = isset($_GET['tvrtke_page']) && is_scalar($_GET['tvrtke_page']) ? max(1, min(10000, absint($_GET['tvrtke_page']))) : 1;
        $form = '<form method="get" class="tvrtke-search" role="search"><label for="tvrtke-q">' . esc_html__('Naziv tvrtke, OIB ili MBS', 'tvrtke-registar') . '</label> ';
        $form .= '<input id="tvrtke-q" name="tvrtke_q" type="search" value="' . esc_attr($q) . '" maxlength="100" /> ';
        $form .= '<button type="submit">' . esc_html__('Pretraži', 'tvrtke-registar') . '</button></form>';
        if ($q === '') return $form . '<p>' . esc_html__('Unesite naziv ili identifikator za početak pretraživanja.', 'tvrtke-registar') . '</p>';
        $data = self::fetch($q, $page);
        if (is_wp_error($data)) return $form . '<p role="alert">' . esc_html($data->get_error_message()) . '</p>';
        $output = $form . '<div class="tvrtke-results">';
        if (empty($data['items'])) $output .= '<p>' . esc_html__('Nema rezultata u trenutačno dostupnom skupu podataka.', 'tvrtke-registar') . '</p>';
        foreach (array_slice($data['items'], 0, 20) as $item) {
            if (!is_array($item)) continue;
            $name = (string) ($item['name'] ?? $item['mbs'] ?? '');
            $output .= '<article class="tvrtke-result"><h3>' . esc_html($name) . '</h3><p>';
            $output .= esc_html__('OIB:', 'tvrtke-registar') . ' ' . esc_html((string) ($item['oib'] ?? '—')) . ' · ';
            $output .= esc_html__('MBS:', 'tvrtke-registar') . ' ' . esc_html((string) ($item['mbs'] ?? '—'));
            $output .= '</p><p>' . esc_html((string) ($item['settlement'] ?? '')) . '</p></article>';
        }
        if ($page > 1) {
            $url = add_query_arg(['tvrtke_q' => $q, 'tvrtke_page' => $page - 1], remove_query_arg(['tvrtke_q', 'tvrtke_page']));
            $output .= '<a href="' . esc_url($url) . '">' . esc_html__('Prethodna', 'tvrtke-registar') . '</a> ';
        }
        if (!empty($data['has_more'])) {
            $url = add_query_arg(['tvrtke_q' => $q, 'tvrtke_page' => $page + 1], remove_query_arg(['tvrtke_q', 'tvrtke_page']));
            $output .= '<a href="' . esc_url($url) . '">' . esc_html__('Sljedeća', 'tvrtke-registar') . '</a>';
        }
        return $output . '</div><p><small>' . esc_html__('Podaci se informativno prikazuju iz lokalno sinkronizirane baze Sudskog registra.', 'tvrtke-registar') . '</small></p>';
    }
}

Tvrtke_Registar::init();
