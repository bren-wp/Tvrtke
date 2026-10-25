<?php
/**
 * Plugin Name: TVRTKE – Poslovni registar
 * Description: Pretraživanje subjekata Sudskog registra preko centralnog API-ja tvrtke.com.hr.
 * Version: 0.2.0
 * Requires PHP: 8.2
 * Requires at least: 6.4
 * Text Domain: tvrtke-registar
 * License: MIT
 */

if (!defined('ABSPATH')) exit;

final class Tvrtke_Registar
{
    private const OPTION = 'tvrtke_registar_api_url';
    private const DEFAULT_API = 'https://api.tvrtke.com.hr';

    public static function init(): void
    {
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_menu', [self::class, 'menu']);
        add_shortcode('tvrtke_pretraga', [self::class, 'shortcode']);
    }

    public static function register_settings(): void
    {
        register_setting('tvrtke_registar', self::OPTION, [
            'type' => 'string', 'default' => self::DEFAULT_API,
            'sanitize_callback' => [self::class, 'validate_url'],
        ]);
    }

    public static function validate_url($value): string
    {
        $url = untrailingslashit(esc_url_raw((string) $value, ['https']));
        if (!$url || wp_parse_url($url, PHP_URL_SCHEME) !== 'https' ||
            wp_parse_url($url, PHP_URL_USER) !== null || wp_parse_url($url, PHP_URL_PASS) !== null ||
            wp_parse_url($url, PHP_URL_QUERY) !== null || wp_parse_url($url, PHP_URL_FRAGMENT) !== null) {
            add_settings_error(self::OPTION, 'invalid', __('Potrebna je valjana HTTPS adresa API-ja.', 'tvrtke-registar'));
            return (string) get_option(self::OPTION, self::DEFAULT_API);
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
        echo '<input class="regular-text" id="tvrtke-api" name="' . esc_attr(self::OPTION) . '" value="' . esc_attr((string) get_option(self::OPTION, self::DEFAULT_API)) . '" type="url" required />';
        echo '</td></tr></table>';
        submit_button();
        echo '</form><p><code>[tvrtke_pretraga]</code></p></div>';
    }

    private static function request(string $endpoint, array $params = []): array|WP_Error
    {
        $base = (string) get_option(self::OPTION, self::DEFAULT_API);
        $url = add_query_arg($params, $base . $endpoint);
        $key = 'tvrtke_' . md5($url);
        $cached = get_transient($key);
        if (is_array($cached)) return $cached;
        $response = wp_safe_remote_get($url, [
            'timeout' => 8, 'redirection' => 0, 'limit_response_size' => 200000,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($response)) return $response;
        if (wp_remote_retrieve_response_code($response) !== 200) {
            return new WP_Error('tvrtke_remote', __('Servis trenutačno nije dostupan ili subjekt nije pronađen.', 'tvrtke-registar'));
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body)) return new WP_Error('tvrtke_invalid', __('Neispravan odgovor API-ja.', 'tvrtke-registar'));
        set_transient($key, $body, 5 * MINUTE_IN_SECONDS);
        return $body;
    }

    /** @return string */
    public static function shortcode(): string
    {
        wp_enqueue_style('tvrtke-registar', plugins_url('assets/search.css', __FILE__), [], '0.2.0');
        $get = static function (string $key, int $max = 100): string {
            $raw = $_GET[$key] ?? '';
            return is_string($raw) ? mb_substr(trim(sanitize_text_field(wp_unslash($raw))), 0, $max) : '';
        };
        $query = $get('tvrtke_q');
        $county = $get('tvrtke_county');
        $activity = $get('tvrtke_activity');
        $status = $get('tvrtke_status', 1);
        if (!in_array($status, ['', '0', '1'], true)) $status = '';
        $page = isset($_GET['tvrtke_page']) && is_scalar($_GET['tvrtke_page']) ? max(1, min(10000, absint($_GET['tvrtke_page']))) : 1;
        $mbs = $get('tvrtke_mbs', 9);
        $id = wp_unique_id('tvrtke-');
        $output = '<section class="tvrtke-registar" aria-label="' . esc_attr__('Pretraživanje poslovnih subjekata', 'tvrtke-registar') . '">';
        $output .= '<form method="get" class="tvrtke-form" role="search">';
        $output .= '<div><label for="' . esc_attr($id) . '-q">' . esc_html__('Tvrtka, OIB ili MBS', 'tvrtke-registar') . '</label><input id="' . esc_attr($id) . '-q" type="search" name="tvrtke_q" value="' . esc_attr($query) . '" maxlength="100" placeholder="' . esc_attr__('Unesite naziv ili identifikator', 'tvrtke-registar') . '"></div>';
        $output .= '<div><label for="' . esc_attr($id) . '-county">' . esc_html__('Županija', 'tvrtke-registar') . '</label><input id="' . esc_attr($id) . '-county" name="tvrtke_county" value="' . esc_attr($county) . '" maxlength="100"></div>';
        $output .= '<div><label for="' . esc_attr($id) . '-act">' . esc_html__('Djelatnost ili NKD šifra', 'tvrtke-registar') . '</label><input id="' . esc_attr($id) . '-act" name="tvrtke_activity" value="' . esc_attr($activity) . '" maxlength="100"></div>';
        $output .= '<div><label for="' . esc_attr($id) . '-status">' . esc_html__('Status', 'tvrtke-registar') . '</label><select id="' . esc_attr($id) . '-status" name="tvrtke_status">';
        foreach (['' => __('Svi', 'tvrtke-registar'), '1' => __('Aktivni', 'tvrtke-registar'), '0' => __('Brisani / neaktivni', 'tvrtke-registar')] as $v => $label) {
            $output .= '<option value="' . esc_attr((string)$v) . '" ' . selected($status, (string)$v, false) . '>' . esc_html($label) . '</option>';
        }
        $output .= '</select></div><button type="submit">' . esc_html__('Pretraži', 'tvrtke-registar') . '</button></form>';

        if ($mbs !== '') {
            if (!preg_match('/^\d{1,9}$/D', $mbs)) return $output . '<p role="alert">' . esc_html__('Neispravan MBS.', 'tvrtke-registar') . '</p></section>';
            $detail = self::request('/v1/subjects/' . str_pad($mbs, 9, '0', STR_PAD_LEFT));
            if (is_wp_error($detail)) return $output . '<p role="alert">' . esc_html($detail->get_error_message()) . '</p></section>';
            $item = $detail['item'] ?? null;
            if (!is_array($item)) return $output . '<p role="alert">' . esc_html__('Podaci nisu dostupni.', 'tvrtke-registar') . '</p></section>';
            $output .= '<div class="tvrtke-detail"><h2>' . esc_html((string)($item['name'] ?? '')) . '</h2><dl>';
            foreach (['mbs' => 'MBS', 'oib' => 'OIB', 'legal_form' => 'Pravni oblik', 'county' => 'Županija', 'settlement' => 'Mjesto', 'street' => 'Ulica', 'house_number' => 'Kućni broj', 'datum_osnivanja' => 'Datum osnivanja'] as $field => $label) {
                $output .= '<dt>' . esc_html($label) . '</dt><dd>' . esc_html((string)($item[$field] ?? '—')) . '</dd>';
            }
            $output .= '</dl><h3>' . esc_html__('Registrirane djelatnosti', 'tvrtke-registar') . '</h3><ul>';
            foreach (array_slice((array)($item['activities'] ?? []), 0, 100) as $row) {
                if (!is_array($row)) continue;
                $output .= '<li>' . esc_html((string)(($row['activity_text'] ?? null) ?: (($row['nkd_name'] ?? null) ?: (($row['nkd_code'] ?? null) ?: '—')))) . '</li>';
            }
            $output .= '</ul></div>';
            if (!empty($item['partial'])) $output .= self::partialNotice();
            return $output . '</section>';
        }
        if ($query === '' && $county === '' && $activity === '' && $status === '') {
            return $output . '<p>' . esc_html__('Unesite kriterij za početak pretraživanja.', 'tvrtke-registar') . '</p></section>';
        }
        $data = self::request('/v1/subjects', [
            'q' => $query, 'county' => $county, 'activity' => $activity, 'status' => $status,
            'page' => $page, 'limit' => 20,
        ]);
        if (is_wp_error($data)) return $output . '<p role="alert">' . esc_html($data->get_error_message()) . '</p></section>';
        if (!isset($data['items']) || !is_array($data['items'])) return $output . '<p role="alert">' . esc_html__('Neispravan odgovor API-ja.', 'tvrtke-registar') . '</p></section>';
        $output .= '<div class="tvrtke-results" aria-live="polite">';
        if (empty($data['items'])) $output .= '<p>' . esc_html__('Nema rezultata u dostupnom skupu.', 'tvrtke-registar') . '</p>';
        foreach (array_slice($data['items'], 0, 20) as $row) {
            if (!is_array($row)) continue;
            $itemMbs = (string)($row['mbs'] ?? '');
            $url = add_query_arg(['tvrtke_mbs' => $itemMbs], remove_query_arg(['tvrtke_mbs', 'tvrtke_page']));
            $output .= '<article class="tvrtke-result"><h3><a href="' . esc_url($url) . '">' . esc_html((string)($row['name'] ?? $itemMbs)) . '</a></h3>';
            $output .= '<p>' . esc_html((string)($row['legal_form_short'] ?? '')) . ' · ' . esc_html((string)($row['settlement'] ?? '')) . '</p>';
            $output .= '<p><strong>MBS:</strong> ' . esc_html($itemMbs) . ' &nbsp; <strong>OIB:</strong> ' . esc_html((string)($row['oib'] ?? '—')) . '</p></article>';
        }
        $output .= '</div><nav class="tvrtke-pages" aria-label="' . esc_attr__('Stranice rezultata', 'tvrtke-registar') . '">';
        if ($page > 1) $output .= '<a href="' . esc_url(add_query_arg('tvrtke_page', $page - 1, remove_query_arg('tvrtke_mbs'))) . '">' . esc_html__('Prethodna', 'tvrtke-registar') . '</a>';
        if (!empty($data['has_more'])) $output .= '<a href="' . esc_url(add_query_arg('tvrtke_page', $page + 1, remove_query_arg('tvrtke_mbs'))) . '">' . esc_html__('Sljedeća', 'tvrtke-registar') . '</a>';
        $output .= '</nav>';
        if (!empty($data['partial'])) $output .= self::partialNotice();
        return $output . '<p class="tvrtke-source">' . esc_html__('Informativni podaci iz lokalno sinkronizirane baze Sudskog registra RH.', 'tvrtke-registar') . '</p></section>';
    }

    private static function partialNotice(): string
    {
        return '<p class="tvrtke-warning" role="status">' . esc_html__('Prikazan je djelomično uvezen skup podataka. Rezultati ne obuhvaćaju nužno sve subjekte.', 'tvrtke-registar') . '</p>';
    }
}

Tvrtke_Registar::init();
