<?php
/**
 * Plugin Name: TVRTKE – Poslovni registar
 * Description: Hrvatski poslovni imenik za pretraživanje tvrtki, obrta, udruga i zaklada iz povezanih javnih registara.
 * Version: 0.10.0
 * Author: Brendigo
 * Plugin URI: https://tvrtke.com.hr
 * License URI: https://opensource.org/license/mit
 * Requires PHP: 8.2
 * Requires at least: 6.4
 * Text Domain: tvrtke-registar
 * License: MIT
 */

if (!defined('ABSPATH')) exit;

final class Tvrtke_Registar
{
    private const OPTION = 'tvrtke_registar_api_url';
    private const CONSENT = 'tvrtke_registar_enabled';
    private const DEFAULT_API = 'https://api.tvrtke.com.hr';

    public static function init(): void
    {
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_enqueue_scripts', [self::class, 'admin_assets']);
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_init', [self::class, 'privacy_information']);
        add_shortcode('tvrtke_pretraga', [self::class, 'shortcode']);
        add_action('init', [self::class, 'register_block']);
    }

    public static function privacy_information(): void
    {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }
        $description = __(
            'Kada administrator uključi TVRTKE uslugu, kriteriji pretraživanja poslovnih subjekata šalju se s WordPress poslužitelja na konfigurirani TVRTKE API putem HTTPS-a. API može obrađivati standardne HTTP metapodatke, uključujući IP adresu poslužitelja. Plugin privremeno sprema odgovore putem WordPress transienta do 24 sata, prema postavkama administratora. Plugin ne postavlja kolačiće niti izravno šalje posjetiteljev IP kao parametar.',
            'tvrtke-registar'
        );
        wp_add_privacy_policy_content('TVRTKE – Poslovni registar', wp_kses_post(wpautop($description)));
    }

    public static function register_block(): void
    {
        wp_register_script(
            'tvrtke-registar-editor',
            plugins_url('blocks/editor.js', __FILE__),
            ['wp-blocks', 'wp-element', 'wp-i18n'],
            '0.10.0',
            true
        );
        register_block_type(__DIR__ . '/blocks', ['render_callback' => [self::class, 'shortcode']]);
    }

    public static function register_settings(): void
    {
        register_setting('tvrtke_registar', self::CONSENT, [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        register_setting('tvrtke_registar', self::OPTION, [
            'type' => 'string', 'default' => self::DEFAULT_API,
            'sanitize_callback' => [self::class, 'validate_url'],
        ]);
        register_setting('tvrtke_registar', 'tvrtke_registar_view', [
            'type' => 'string', 'default' => 'list',
            'sanitize_callback' => static fn($value): string =>
                in_array($value, ['list', 'cards'], true) ? $value : 'list',
        ]);
        register_setting('tvrtke_registar', 'tvrtke_registar_per_page', [
            'type' => 'integer', 'default' => 20,
            'sanitize_callback' => static fn($value): int =>
                min(50, max(5, (int)$value)),
        ]);
        register_setting('tvrtke_registar', 'tvrtke_registar_cache_minutes', [
            'type' => 'integer', 'default' => 5,
            'sanitize_callback' => static fn($value): int =>
                min(1440, max(1, (int)$value)),
        ]);
        register_setting('tvrtke_registar', 'tvrtke_registar_fields', [
            'type' => 'array', 'default' => ['settlement', 'activity'],
            'sanitize_callback' => static fn($value): array =>
                array_values(array_intersect(
                    ['settlement', 'activity', 'oib'],
                    is_array($value) ? array_filter($value, 'is_string') : []
                )),
        ]);
    }

    public static function validate_url($value): string
    {
        $url = untrailingslashit(esc_url_raw((string) $value, ['https']));
        if (!$url || wp_parse_url($url, PHP_URL_SCHEME) !== 'https' ||
            wp_parse_url($url, PHP_URL_HOST) === null || wp_parse_url($url, PHP_URL_USER) !== null || wp_parse_url($url, PHP_URL_PASS) !== null ||
            wp_parse_url($url, PHP_URL_QUERY) !== null || wp_parse_url($url, PHP_URL_FRAGMENT) !== null) {
            add_settings_error(self::OPTION, 'invalid', __('Potrebna je valjana HTTPS adresa API-ja.', 'tvrtke-registar'));
            return (string) get_option(self::OPTION, self::DEFAULT_API);
        }
        return $url;
    }

    public static function menu(): void
    {
        add_menu_page(
            __('TVRTKE – poslovni imenik', 'tvrtke-registar'),
            'TVRTKE',
            'manage_options',
            'tvrtke-registar',
            [self::class, 'settings_page'],
            'dashicons-chart-area',
            28
        );
        add_submenu_page('tvrtke-registar', __('Postavke', 'tvrtke-registar'),
            __('Postavke', 'tvrtke-registar'), 'manage_options', 'tvrtke-registar',
            [self::class, 'settings_page']);
        add_submenu_page('tvrtke-registar', __('Pretraga', 'tvrtke-registar'),
            __('Pretraga', 'tvrtke-registar'), 'manage_options', 'tvrtke-pretraga',
            [self::class, 'admin_search_page']);
        add_submenu_page('tvrtke-registar', __('Uvoz / Izvoz', 'tvrtke-registar'),
            __('Uvoz / Izvoz', 'tvrtke-registar'), 'manage_options', 'tvrtke-izvori',
            [self::class, 'admin_sources_page']);
        add_submenu_page('tvrtke-registar', __('Statistika', 'tvrtke-registar'),
            __('Statistika', 'tvrtke-registar'), 'manage_options', 'tvrtke-statistika',
            [self::class, 'admin_statistics_page']);
        add_submenu_page('tvrtke-registar', __('Pomoć', 'tvrtke-registar'),
            __('Pomoć', 'tvrtke-registar'), 'manage_options', 'tvrtke-pomoc',
            [self::class, 'admin_help_page']);
    }

    public static function admin_assets(string $hook): void
    {
        if (!in_array($hook, ['toplevel_page_tvrtke-registar',
            'tvrtke_page_tvrtke-pretraga', 'tvrtke_page_tvrtke-izvori',
            'tvrtke_page_tvrtke-statistika', 'tvrtke_page_tvrtke-pomoc'], true)) return;
        wp_enqueue_style('tvrtke-admin', plugins_url('assets/admin.css', __FILE__), [], '0.10.0');
        wp_enqueue_script('tvrtke-admin', plugins_url('assets/admin.js', __FILE__), [], '0.10.0', true);
    }

    private static function admin_page_header(string $title, string $description): void
    {
        echo '<div class="wrap tvrtke-admin"><header class="tvrtke-admin-hero">';
        echo '<div class="tvrtke-brand"><img src="' .
            esc_url(plugins_url('assets/logo-mark.svg', __FILE__)) .
            '" width="73" height="73" alt="" />';
        echo '<div><strong>TVRTKE</strong><small>' .
            esc_html__('POSLOVNI IMENIK HRVATSKE', 'tvrtke-registar') .
            '</small></div></div><div class="tvrtke-hero-copy"><h1>' .
            esc_html($title) . '</h1><p>' . esc_html($description) .
            '</p></div></header>';
        self::admin_navigation();
    }

    /** Shared navigation on all administrator screens, backed by real pages. */
    private static function admin_navigation(): void
    {
        $active = isset($GLOBALS['plugin_page']) && is_string($GLOBALS['plugin_page'])
            ? sanitize_key($GLOBALS['plugin_page']) : 'tvrtke-registar';
        $tabs = [
            'tvrtke-registar' => __('Postavke', 'tvrtke-registar'),
            'tvrtke-pretraga' => __('Pretraga', 'tvrtke-registar'),
            'tvrtke-izvori' => __('Uvoz / Izvoz', 'tvrtke-registar'),
            'tvrtke-statistika' => __('Statistika', 'tvrtke-registar'),
            'tvrtke-pomoc' => __('Pomoć', 'tvrtke-registar'),
        ];
        echo '<nav class="tvrtke-tabs tvrtke-page-tabs" aria-label="' .
            esc_attr__('TVRTKE administracijske stranice', 'tvrtke-registar') . '">';
        foreach ($tabs as $slug => $label) {
            $href = menu_page_url($slug, false);
            if (!$href) continue;
            echo '<a href="' . esc_url($href) . '"' .
                ($active === $slug ? ' class="is-current" aria-current="page"' : '') .
                '>' . esc_html($label) . '</a>';
        }
        echo '</nav>';
    }

    public static function admin_search_page(): void
    {
        if (!current_user_can('manage_options')) return;
        self::admin_page_header(
            __('Pretraživanje poslovnih subjekata', 'tvrtke-registar'),
            __('Provjerite izgled stvarne tražilice i rezultate iz objavljenih izvora.', 'tvrtke-registar')
        );
        echo '<section class="tvrtke-card">';
        // Public shortcode sanitizes its own read-only GET filters.
        echo self::shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</section></div>';
    }

    private static function admin_sources(): array|WP_Error
    {
        $response = self::request('/v1/sources');
        if (is_wp_error($response)) return $response;
        return isset($response['sources']) && is_array($response['sources'])
            ? $response['sources'] : new WP_Error('tvrtke_invalid_sources',
                __('Neispravni podaci o službenim izvorima.', 'tvrtke-registar'));
    }

    private static function render_sources_table(array $sources): void
    {
        if ($sources === []) {
            echo '<p class="tvrtke-warning">' .
                esc_html__('Još nema objavljenih vanjskih registara. Podaci se uvoze isključivo na privatnom poslužitelju.', 'tvrtke-registar') .
                '</p>';
            return;
        }
        echo '<div class="tvrtke-admin-table-scroll"><table class="widefat striped"><thead><tr>';
        foreach ([
            __('Registar', 'tvrtke-registar'),
            __('Objavljenih zapisa', 'tvrtke-registar'),
            __('Pokrivenost', 'tvrtke-registar'),
            __('Zadnja objava (UTC)', 'tvrtke-registar'),
        ] as $label) echo '<th scope="col">' . esc_html($label) . '</th>';
        echo '</tr></thead><tbody>';
        foreach (array_slice($sources, 0, 20) as $row) {
            if (!is_array($row)) continue;
            echo '<tr><th scope="row">' . esc_html((string)($row['source_type'] ?? '—')) .
                '</th><td>' . esc_html((string)($row['record_count'] ?? '—')) .
                '</td><td>' . esc_html((string)($row['coverage'] ?? '—')) .
                '</td><td>' . esc_html((string)($row['published_at_utc'] ?? '—')) .
                '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public static function admin_sources_page(): void
    {
        if (!current_user_can('manage_options')) return;
        self::admin_page_header(
            __('Službeni izvori i ažuriranje', 'tvrtke-registar'),
            __('Uvid u objavljene registre. Sinkronizaciju i prava pristupa kontrolira privatni backend.', 'tvrtke-registar')
        );
        echo '<section class="tvrtke-card"><h2>' .
            esc_html__('Izvori podataka', 'tvrtke-registar') . '</h2>';
        $sources = self::admin_sources();
        if (is_wp_error($sources)) {
            echo '<p class="tvrtke-warning">' . esc_html($sources->get_error_message()) . '</p>';
        } else {
            self::render_sources_table($sources);
        }
        echo '<div class="tvrtke-hint">' .
            esc_html__('Uvoz i izvoz službenih izvora ne izvode se iz WordPress administracije. Zaštita Sudreg i MySQL vjerodajnica zahtijeva privatni CLI i cron na vašem poslužitelju.', 'tvrtke-registar') .
            '</div></section></div>';
    }

    public static function admin_statistics_page(): void
    {
        if (!current_user_can('manage_options')) return;
        self::admin_page_header(
            __('Statistika objavljenih registara', 'tvrtke-registar'),
            __('Samo stvarno objavljeni podaci; bez izmišljenih brojača, korisnika ili pretraga.', 'tvrtke-registar')
        );
        echo '<section class="tvrtke-card"><h2>' .
            esc_html__('Pokrivenost i broj zapisa', 'tvrtke-registar') . '</h2>';
        $sources = self::admin_sources();
        if (is_wp_error($sources)) {
            echo '<p class="tvrtke-warning">' . esc_html($sources->get_error_message()) . '</p>';
        } else {
            $total = 0;
            foreach ($sources as $source) {
                if (!is_array($source)) continue;
                $value = $source['record_count'] ?? null;
                if (is_numeric($value) && (float)$value >= 0) $total += (int)$value;
            }
            echo '<p class="tvrtke-stats-value">' . esc_html(number_format_i18n($total)) .
                '</p><p>' .
                esc_html__('Zapisa iz trenutno objavljenih vanjskih CSV izvora; Sudski registar se prikazuje zasebno i nije uključen u taj zbroj.', 'tvrtke-registar') .
                '</p>';
            self::render_sources_table($sources);
        }
        echo '</section></div>';
    }

    public static function admin_help_page(): void
    {
        if (!current_user_can('manage_options')) return;
        self::admin_page_header(
            __('TVRTKE — Pomoć', 'tvrtke-registar'),
            __('Upute za korištenje dodatka i odgovoran rad sa službenim podacima.', 'tvrtke-registar')
        );
        echo '<section class="tvrtke-card"><h2>' .
            esc_html__('Postavljanje', 'tvrtke-registar') . '</h2>';
        echo '<p>' .
            esc_html__('U postavkama upišite adresu centralnog HTTPS API-ja i izričito uključite vanjsku uslugu. Pretraživanje koristi samo javno objavljene zapise.', 'tvrtke-registar') .
            '</p><p><code>[tvrtke_pretraga]</code></p><p>' .
            esc_html__('U uređivaču blokova možete koristiti dinamički blok TVRTKE pretraga.', 'tvrtke-registar') .
            '</p><a href="https://github.com/bren-wp/Tvrtke" target="_blank" rel="noopener noreferrer">' .
            esc_html__('Dokumentacija i podrška', 'tvrtke-registar') .
            '</a></section></div>';
    }

    public static function settings_page(): void
    {
        if (!current_user_can('manage_options')) return;
        $enabled = (bool)get_option(self::CONSENT, false);
        $view = (string)get_option('tvrtke_registar_view', 'list');
        $perPage = min(50, max(5, (int)get_option('tvrtke_registar_per_page', 20)));
        $cacheMinutes = min(1440, max(1, (int)get_option('tvrtke_registar_cache_minutes', 5)));
        $enabledFields = get_option('tvrtke_registar_fields', ['settlement', 'activity']);
        if (!is_array($enabledFields)) $enabledFields = ['settlement', 'activity'];
        $logo = plugins_url('assets/logo-mark.svg', __FILE__);

        echo '<div class="wrap tvrtke-admin">';
        echo '<header class="tvrtke-admin-hero">';
        echo '<div class="tvrtke-brand"><img src="' . esc_url($logo) . '" width="73" height="73" alt="TVRTKE" />';
        echo '<div><strong>TVRTKE</strong><small>' . esc_html__('POSLOVNI IMENIK HRVATSKE', 'tvrtke-registar') . '</small></div></div>';
        echo '<div class="tvrtke-hero-copy"><h1>' . esc_html__('Sve poslovne informacije Hrvatske na jednom mjestu.', 'tvrtke-registar') . '</h1>';
        echo '<p>' . esc_html__('Povežite WordPress sa svojim centralnim API-jem i prilagodite tražilicu tvrtki, obrta, udruga i zaklada.', 'tvrtke-registar') . '</p></div>';
        echo '<div class="tvrtke-server-badge"><span>' . esc_html__('Vanjski API', 'tvrtke-registar') . '</span>';
        echo '<strong>' . esc_html($enabled ? __('Uključeno', 'tvrtke-registar') : __('Isključeno', 'tvrtke-registar')) . '</strong></div></header>';

        echo '<nav class="tvrtke-tabs tvrtke-section-tabs" aria-label="' . esc_attr__('Postavke TVRTKE', 'tvrtke-registar') . '">';
        foreach ([
            'api' => ['⚙', __('API postavke', 'tvrtke-registar')],
            'prikaz' => ['▣', __('Prikaz', 'tvrtke-registar')],
            'privatnost' => ['◇', __('Privatnost', 'tvrtke-registar')],
            'kod' => ['⌘', __('Kratki kod / blok', 'tvrtke-registar')],
        ] as $anchor => $tab) {
            echo '<a href="#tvrtke-' . esc_attr($anchor) . '"' .
                ($anchor === 'api' ? ' class="is-current"' : '') . '><span aria-hidden="true">' .
                esc_html($tab[0]) . '</span>' . esc_html($tab[1]) . '</a>';
        }
        echo '</nav>';
        echo '<form action="options.php" method="post">';
        settings_fields('tvrtke_registar');
        echo '<div class="tvrtke-admin-layout"><div>';
        echo '<section id="tvrtke-api" class="tvrtke-card"><h2>' . esc_html__('API postavke', 'tvrtke-registar') . '</h2>';
        echo '<p>' . esc_html__('Povežite svoju stranicu s privatno upravljanim centralnim API-jem.', 'tvrtke-registar') . '</p>';
        echo '<div class="tvrtke-row"><div><label for="tvrtke-api">' .
            esc_html__('HTTPS adresa API-ja', 'tvrtke-registar') . '</label><p class="description">' .
            esc_html__('Adresa centralne javne tražilice. Bez Sudreg tajni.', 'tvrtke-registar') . '</p></div>';
        echo '<input class="regular-text" id="tvrtke-api" name="' . esc_attr(self::OPTION) .
            '" value="' . esc_attr((string)get_option(self::OPTION, self::DEFAULT_API)) .
            '" type="url" pattern="https://.*" required /></div>';
        echo '<div class="tvrtke-row"><div><label for="tvrtke-external">' .
            esc_html__('Omogući vanjski servis', 'tvrtke-registar') . '</label><p class="description">' .
            esc_html__('Posjetiteljeve kriterije pretrage prosljeđuje WordPress poslužitelj putem HTTPS-a.', 'tvrtke-registar') . '</p></div>';
        echo '<div class="tvrtke-inline-switch"><input type="hidden" name="' . esc_attr(self::CONSENT) . '" value="0">';
        echo '<input id="tvrtke-external" type="checkbox" name="' . esc_attr(self::CONSENT) .
            '" value="1" ' . checked($enabled, true, false) . ' />';
        echo '<label for="tvrtke-external">' .
            esc_html__('Dopuštam vezu s konfiguriranim servisom', 'tvrtke-registar') . '</label></div></div>';
        echo '<div class="tvrtke-row"><div><label for="tvrtke-cache-minutes">' .
            esc_html__('Trajanje predmemorije', 'tvrtke-registar') . '</label>';
        echo '<p class="description">' .
            esc_html__('Koliko minuta javna tražilica čuva rezultat na WordPress poslužitelju.', 'tvrtke-registar') . '</p></div>';
        echo '<div class="tvrtke-duration"><input type="number" min="1" max="1440" step="1" id="tvrtke-cache-minutes" name="tvrtke_registar_cache_minutes" value="' .
            esc_attr((string)$cacheMinutes) . '" /> <span>' . esc_html__('minuta (1–1440)', 'tvrtke-registar') . '</span></div></div>';
        echo '<div class="tvrtke-hint">' .
            esc_html__('Sudreg OAuth2 Client ID, Client Secret i MySQL lozinke smiju biti pohranjeni samo na privatnom backend poslužitelju. WordPress dodatak ne zahtijeva te ključeve.', 'tvrtke-registar') .
            '</div></section>';

        echo '<section id="tvrtke-prikaz" class="tvrtke-card"><h2>' .
            esc_html__('Prikaz rezultata', 'tvrtke-registar') . '</h2><p>' .
            esc_html__('Postavke neposredno utječu na javnu tražilicu i Gutenberg blok.', 'tvrtke-registar') . '</p>';
        echo '<div class="tvrtke-row"><div><label for="tvrtke-view">' .
            esc_html__('Raspored rezultata', 'tvrtke-registar') . '</label></div>';
        echo '<select id="tvrtke-view" name="tvrtke_registar_view">';
        foreach (['list' => __('Popis', 'tvrtke-registar'), 'cards' => __('Kartice', 'tvrtke-registar')] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($view, $value, false) . '>' .
                esc_html($label) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="tvrtke-row"><div><label for="tvrtke-page-size">' .
            esc_html__('Rezultata po stranici', 'tvrtke-registar') . '</label></div>';
        echo '<input id="tvrtke-page-size" type="number" min="5" max="50" step="1" name="tvrtke_registar_per_page" value="' .
            esc_attr((string)$perPage) . '" /></div>';
        echo '<div class="tvrtke-row"><div><strong>' . esc_html__('Istaknuti podaci', 'tvrtke-registar') .
            '</strong><p class="description">' .
            esc_html__('Naziv i status uvijek su vidljivi. Odaberite dodatne podatke u popisu.', 'tvrtke-registar') . '</p></div>';
        echo '<div class="tvrtke-field-choices">';
        echo '<input type="hidden" name="tvrtke_registar_fields[]" value="">';
        foreach ([
            'settlement' => __('Sjedište', 'tvrtke-registar'),
            'activity' => __('Djelatnost', 'tvrtke-registar'),
            'oib' => __('OIB pravnog subjekta', 'tvrtke-registar'),
        ] as $field => $label) {
            echo '<label><input type="checkbox" name="tvrtke_registar_fields[]" value="' .
                esc_attr($field) . '"' . checked(in_array($field, $enabledFields, true), true, false) .
                ' /> ' . esc_html($label) . '</label>';
        }
        echo '</div></div></section>';

        echo '<section id="tvrtke-privatnost" class="tvrtke-card"><h2>' .
            esc_html__('Privatnost i podaci', 'tvrtke-registar') . '</h2>';
        echo '<p>' . esc_html__('Pretrage se obrađuju na vašem WordPress poslužitelju i centralnom API-ju; bez javnog pristupa internim registracijskim ključevima.', 'tvrtke-registar') . '</p>';
        echo '<div class="tvrtke-hint">' .
            esc_html__('Korisnici vide samo javno dopuštene podatke koje API vraća. Potrebno je objaviti politiku privatnosti i uvjete vanjske usluge.', 'tvrtke-registar') .
            '</div></section>';
        submit_button(__('Spremi postavke', 'tvrtke-registar'));
        echo '</div><aside>';

        echo '<section class="tvrtke-card"><h2>' .
            esc_html__('Pregled pretraživanja', 'tvrtke-registar') . '</h2><p>' .
            esc_html__('Vizualni pregled bloka bez izmišljenih registarskih zapisa.', 'tvrtke-registar') . '</p>';
        echo '<div class="tvrtke-admin-preview"><div class="tvrtke-preview-head"><img src="' . esc_url($logo) .
            '" width="52" height="52" alt="" />TVRTKE</div>';
        echo '<div class="tvrtke-preview-search"><span>' .
            esc_html__('Pretraži naziv ili registracijski broj…', 'tvrtke-registar') .
            '</span><b>' . esc_html__('Pretraži', 'tvrtke-registar') . '</b></div>';
        echo '<div class="tvrtke-preview-cats">';
        foreach ([
            'sudreg' => __('Tvrtke', 'tvrtke-registar'),
            'obrt' => __('Obrti', 'tvrtke-registar'),
            'udruga' => __('Udruge', 'tvrtke-registar'),
            'zaklada' => __('Zaklade', 'tvrtke-registar'),
        ] as $kind => $label) {
            echo '<span>' . wp_kses(self::icon($kind), [
                'svg' => [
                    'class' => true, 'viewbox' => true, 'width' => true, 'height' => true,
                    'aria-hidden' => true, 'focusable' => true, 'xmlns' => true,
                ],
                'path' => [
                    'd' => true, 'fill' => true, 'stroke' => true,
                    'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true,
                ],
            ]) . esc_html($label) . '</span>';
        }
        echo '</div></div></section>';

        echo '<section id="tvrtke-kod" class="tvrtke-card"><h2>' .
            esc_html__('Kratki kod / Gutenberg blok', 'tvrtke-registar') . '</h2><p>' .
            esc_html__('Umetnite tražilicu u bilo koju stranicu ili objavu.', 'tvrtke-registar') . '</p>';
        echo '<div class="tvrtke-shortcode"><code>[tvrtke_pretraga]</code>';
        echo '<button type="button" class="button tvrtke-copy-button" data-tvrtke-copy="1">' .
            esc_html__('Kopiraj', 'tvrtke-registar') . '</button></div>';
        echo '<p class="tvrtke-note" aria-live="polite" data-tvrtke-copy-status="1"></p>';
        echo '<p class="tvrtke-note">' .
            esc_html__('U uređivaču blokova odaberite „TVRTKE pretraga”. Nisu potrebni korisnički API ključevi.', 'tvrtke-registar') . '</p></section>';

        echo '<section class="tvrtke-card"><h2>' . esc_html__('Podrška', 'tvrtke-registar') . '</h2>';
        echo '<p>' . esc_html__('Dokumentacija za instalaciju, privatnost i službene izvore.', 'tvrtke-registar') . '</p>';
        echo '<a href="https://github.com/bren-wp/Tvrtke" target="_blank" rel="noopener noreferrer">' .
            esc_html__('Pogledaj TVRTKE dokumentaciju ↗', 'tvrtke-registar') . '</a></section>';
        echo '</aside></div></form></div>';
    }

    private static function request(string $endpoint, array $params = []): array|WP_Error
    {
        if (!(bool) get_option(self::CONSENT, false)) {
            return new WP_Error('tvrtke_disabled', __('Administrator nije aktivirao vanjski TVRTKE API.', 'tvrtke-registar'));
        }
        $base = (string) get_option(self::OPTION, self::DEFAULT_API);
        if (wp_parse_url($base, PHP_URL_SCHEME) !== 'https') {
            return new WP_Error('tvrtke_url', __('Potrebna je valjana HTTPS adresa API-ja.', 'tvrtke-registar'));
        }
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
        $cacheMinutes = min(1440, max(1, (int)get_option('tvrtke_registar_cache_minutes', 5)));
        set_transient($key, $body, $cacheMinutes * MINUTE_IN_SECONDS);
        return $body;
    }

    /**
     * Branded, fixed vector pictograms, shared by every frontend category,
     * result and admin preview. No client-controlled data enters SVG markup.
     */
    private static function icon(string $kind): string
    {
        $paths = [
            'sudreg' => 'M4 21 V6 L12 2 L20 6 V21 M8 9 V10 M12 9 V10 M16 9 V10 M8 14 V15 M12 14 V15 M16 14 V15 M2 21 H22',
            'obrt' => 'M3 9 L5 3 H19 L21 9 V12 C19 14 17 14 15 12 C13 14 11 14 9 12 C7 14 5 14 3 12 Z M5 13 V21 H19 V13 M9 21 V16 H15 V21',
            'udruga' => 'M12 12 A3 3 0 1 0 12 6 A3 3 0 1 0 12 12 M4 21 V18 C4 13 20 13 20 18 V21 M4 11 A2 2 0 1 1 4 7 M20 11 A2 2 0 1 0 20 7 M1 20 V17 C1 15 3 14 5 14 M23 20 V17 C23 15 21 14 19 14',
            'zaklada' => 'M12 21 C10 19 2 13 2 8 A5 5 0 0 1 12 6 A5 5 0 0 1 22 8 C22 13 14 19 12 21 Z',
            'search' => 'M21 21 L16.8 16.8 M19 10.5 A8.5 8.5 0 1 1 2 10.5 A8.5 8.5 0 1 1 19 10.5',
        ];
        $path = $paths[$kind] ?? $paths['sudreg'];
        return '<svg class="tvrtke-icon" viewBox="0 0 24 24" width="24" height="24" ' .
            'aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">' .
            '<path d="' . $path . '" fill="none" stroke="currentColor" stroke-width="1.85" ' .
            'stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    /** @return string Fully escaped public read-only search markup. */
    public static function shortcode(): string
    {
        wp_enqueue_style('tvrtke-registar', plugins_url('assets/search.css', __FILE__), [], '0.10.0');
        // These public GET filters are read-only: no database writes, accounts, or privileged actions.
        // A nonce is deliberately not required so search result links remain shareable.
        $get = static function (string $key, int $max = 100): string {
            // Reject array input rather than passing it to WordPress text sanitizers.
            $raw = isset($_GET[$key]) && is_string($_GET[$key])
                ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
            $clean = trim($raw);
            return function_exists('mb_substr') ? mb_substr($clean, 0, $max) : substr($clean, 0, $max);
        };
        $query = $get('tvrtke_q');
        $county = $get('tvrtke_county');
        $activity = $get('tvrtke_activity');
        $settlement = $get('tvrtke_settlement');
        $registryType = $get('tvrtke_type', 16);
        $types = [
            'all' => __('Svi registri', 'tvrtke-registar'),
            'sudreg' => __('Subjekti Sudskog registra', 'tvrtke-registar'),
            'obrt' => __('Obrti', 'tvrtke-registar'),
            'udruga' => __('Udruge', 'tvrtke-registar'),
            'zaklada' => __('Zaklade', 'tvrtke-registar'),
            'ostalo' => __('Ostali subjekti', 'tvrtke-registar'),
        ];
        if (!array_key_exists($registryType, $types)) $registryType = 'all';
        $legalForm = $get('tvrtke_legal_form', 12);
        if ($legalForm !== '' && !preg_match('/^[0-9]{1,12}$/D', $legalForm)) $legalForm = '';
        $status = $get('tvrtke_status', 1);
        if (!in_array($status, ['', '0', '1'], true)) $status = '';
        $requestedPage = filter_input(INPUT_GET, 'tvrtke_page', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
        $page = is_int($requestedPage) ? $requestedPage : 1;
        $mbs = $get('tvrtke_mbs', 9);
        $ref = $get('tvrtke_ref', 80);
        $id = wp_unique_id('tvrtke-');
        $layout = (string) get_option('tvrtke_registar_view', 'list');
        if (!in_array($layout, ['list', 'cards'], true)) $layout = 'list';
        $perPage = min(50, max(5, (int)get_option('tvrtke_registar_per_page', 20)));
        $enabledFields = get_option('tvrtke_registar_fields', ['settlement', 'activity']);
        if (!is_array($enabledFields)) $enabledFields = ['settlement', 'activity'];
        $output = '<section class="tvrtke-registar" data-view="' . esc_attr($layout) . '" aria-label="' . esc_attr__('Pretraživanje poslovnih subjekata', 'tvrtke-registar') . '">';
        $output .= '<header class="tvrtke-hero"><div class="tvrtke-public-brand">';
        $output .= '<img src="' . esc_url(plugins_url('assets/logo-mark.svg', __FILE__)) .
            '" width="56" height="56" alt="" loading="lazy" />';
        $output .= '<span><strong>TVRTKE</strong><small>' .
            esc_html__('POSLOVNI IMENIK HRVATSKE', 'tvrtke-registar') .
            '</small></span></div>';
        $output .= '<h2>' . esc_html__('Pronađite prave poslovne informacije', 'tvrtke-registar') . '</h2>';
        $output .= '<p>' . esc_html__('Tvrtke, obrti, udruge i zaklade iz dostupnih službenih izvora — na jednom mjestu.', 'tvrtke-registar') . '</p></header>';
        $output .= '<form method="get" class="tvrtke-form" role="search">';
        $output .= '<div class="tvrtke-search-main">';
        $output .= '<div class="tvrtke-search-query"><label for="' . esc_attr($id) . '-q">' .
            esc_html__('Pretraga', 'tvrtke-registar') . '</label><input id="' . esc_attr($id) .
            '-q" type="search" name="tvrtke_q" value="' . esc_attr($query) .
            '" maxlength="100" placeholder="' .
            esc_attr__('Pretraži naziv, OIB ili registracijski broj...', 'tvrtke-registar') . '"></div>';
        $output .= '<div><label for="' . esc_attr($id) . '-type">' .
            esc_html__('Vrsta subjekta', 'tvrtke-registar') .
            '</label><select id="' . esc_attr($id) . '-type" name="tvrtke_type">';
        foreach ($types as $v => $label) {
            $output .= '<option value="' . esc_attr($v) . '" ' .
                selected($registryType, $v, false) . '>' . esc_html($label) . '</option>';
        }
        $output .= '</select></div>';
        $output .= '<div><label for="' . esc_attr($id) . '-county">' .
            esc_html__('Županija', 'tvrtke-registar') .
            '</label><input id="' . esc_attr($id) . '-county" name="tvrtke_county" value="' .
            esc_attr($county) . '" maxlength="100" placeholder="' .
            esc_attr__('Sve županije', 'tvrtke-registar') . '"></div>';
        $output .= '<div><label for="' . esc_attr($id) . '-act">' .
            esc_html__('Djelatnost / NKD', 'tvrtke-registar') .
            '</label><input id="' . esc_attr($id) . '-act" name="tvrtke_activity" value="' .
            esc_attr($activity) . '" maxlength="100" placeholder="' .
            esc_attr__('Sve djelatnosti', 'tvrtke-registar') . '"></div>';
        $output .= '<button class="tvrtke-submit" type="submit">' .
            self::icon('search') . '<span>' . esc_html__('Pretraži', 'tvrtke-registar') . '</span></button></div>';
        $advancedActive = $settlement !== '' || $legalForm !== '' || $status !== '';
        $output .= '<details class="tvrtke-advanced"' . ($advancedActive ? ' open' : '') .
            '><summary>' . esc_html__('Više filtara', 'tvrtke-registar') . '</summary><div class="tvrtke-advanced-grid">';
        $output .= '<div><label for="' . esc_attr($id) . '-place">' .
            esc_html__('Grad / općina', 'tvrtke-registar') .
            '</label><input id="' . esc_attr($id) . '-place" name="tvrtke_settlement" value="' .
            esc_attr($settlement) . '" maxlength="100"></div>';
        $output .= '<div><label for="' . esc_attr($id) . '-legal">' .
            esc_html__('Šifra pravnog oblika', 'tvrtke-registar') .
            '</label><input id="' . esc_attr($id) . '-legal" name="tvrtke_legal_form" value="' .
            esc_attr($legalForm) . '" maxlength="12" inputmode="numeric"></div>';
        $output .= '<div><label for="' . esc_attr($id) . '-status">' .
            esc_html__('Status', 'tvrtke-registar') .
            '</label><select id="' . esc_attr($id) . '-status" name="tvrtke_status">';
        foreach (['' => __('Svi', 'tvrtke-registar'),
            '1' => __('Aktivni', 'tvrtke-registar'),
            '0' => __('Brisani / neaktivni', 'tvrtke-registar')] as $v => $label) {
            $output .= '<option value="' . esc_attr((string)$v) . '" ' .
                selected($status, (string)$v, false) . '>' . esc_html($label) . '</option>';
        }
        $output .= '</select></div></div></details></form>';
        $output .= '<nav class="tvrtke-quick-links" aria-label="' . esc_attr__('Vrste poslovnih subjekata', 'tvrtke-registar') . '">';
        foreach ([
            'sudreg' => __('Tvrtke', 'tvrtke-registar'),
            'obrt' => __('Obrti', 'tvrtke-registar'),
            'udruga' => __('Udruge', 'tvrtke-registar'),
            'zaklada' => __('Zaklade', 'tvrtke-registar'),
        ] as $kind => $categoryLabel) {
            $href = add_query_arg(
                ['tvrtke_type' => $kind, 'tvrtke_page' => 1],
                remove_query_arg(['tvrtke_ref', 'tvrtke_mbs', 'tvrtke_page'])
            );
            $output .= '<a href="' . esc_url($href) . '"' .
                ($registryType === $kind ? ' aria-current="true"' : '') . '><span class="tvrtke-quick-icon">' .
                self::icon($kind) . '</span><span>' . esc_html($categoryLabel) . '</span></a>';
        }
        $output .= '</nav>';

        if ($mbs !== '' || $ref !== '') {
            if ($ref !== '') {
                if (!preg_match('/^(sudreg|obrt|udruga|zaklada|ostalo):([a-f0-9]{64}|[0-9]{9})$/D', $ref, $match)) {
                    return $output . '<p role="alert">' . esc_html__('Neispravan identifikator.', 'tvrtke-registar') . '</p></section>';
                }
                $detail = self::request('/v1/directory/' . $match[1] . '/' . $match[2]);
            } else {
                if (!preg_match('/^\d{1,9}$/D', $mbs)) {
                    return $output . '<p role="alert">' . esc_html__('Neispravan MBS.', 'tvrtke-registar') . '</p></section>';
                }
                $detail = self::request('/v1/directory/sudreg/' . str_pad($mbs, 9, '0', STR_PAD_LEFT));
            }
            if (is_wp_error($detail)) return $output . '<p role="alert">' . esc_html($detail->get_error_message()) . '</p></section>';
            $item = $detail['item'] ?? null;
            if (!is_array($item)) return $output . '<p role="alert">' . esc_html__('Podaci nisu dostupni.', 'tvrtke-registar') . '</p></section>';
            $back = remove_query_arg(['tvrtke_ref', 'tvrtke_mbs']);
            $output .= '<a class="tvrtke-back" href="' . esc_url($back) . '">' . esc_html__('← Povratak na rezultate', 'tvrtke-registar') . '</a>';
            $output .= '<div class="tvrtke-detail"><div class="tvrtke-detail-heading"><h2>' . esc_html((string)($item['name'] ?? '')) . '</h2>';
            $output .= '<p>' . esc_html((string)($types[(string)($item['source_type'] ?? '')] ?? __('Poslovni subjekt', 'tvrtke-registar'))) . ' · ' .
                esc_html((string)($item['status_label'] ?? __('Nepoznat status', 'tvrtke-registar'))) . '</p></div>';
            $output .= '<div class="tvrtke-detail-body"><div class="tvrtke-detail-layout">' .
                '<section class="tvrtke-detail-facts"><h3>' .
                esc_html__('Osnovni podaci', 'tvrtke-registar') . '</h3><dl>';
            foreach (['source_type' => 'Registar', 'source_identifier' => 'Broj u registru', 'mbs' => 'MBS', 'oib' => 'OIB', 'status_label' => 'Status', 'legal_form' => 'Pravni oblik', 'county' => 'Županija', 'settlement' => 'Mjesto', 'street' => 'Ulica', 'house_number' => 'Kućni broj', 'datum_osnivanja' => 'Datum osnivanja', 'datum_brisanja' => 'Datum brisanja / prestanka', 'datum_upisa' => 'Datum upisa u registar', 'datum_statusa' => 'Datum promjene statusa'] as $field => $label) {
                $output .= '<dt>' . esc_html($label) . '</dt><dd>' . esc_html((string)($item[$field] ?? '—')) . '</dd>';
            }
            $output .= '</dl></section>';
            $output .= '<aside class="tvrtke-detail-aside"><h3>' .
                esc_html__('Lokacija i službeni izvori', 'tvrtke-registar') . '</h3>';
            $sourceUrl = isset($item['source_url']) && is_string($item['source_url']) ? $item['source_url'] : '';
            if ($sourceUrl !== '' && wp_parse_url($sourceUrl, PHP_URL_SCHEME) === 'https') {
                $output .= '<p class="tvrtke-source"><a href="' . esc_url($sourceUrl) .
                    '" target="_blank" rel="noopener noreferrer">' .
                    esc_html__('Službeni izvor podataka ↗', 'tvrtke-registar') . '</a></p>';
            }
            $fullAddress = trim(implode(' ', array_filter([
                (string)($item['street'] ?? ''),
                (string)($item['house_number'] ?? ''),
                (string)($item['settlement'] ?? ''),
            ])));
            if ($fullAddress !== '') {
                $mapUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($fullAddress . ', Hrvatska');
                $output .= '<p class="tvrtke-detail-address">' .
                    esc_html($fullAddress) . '</p>';
                $output .= '<p class="tvrtke-source"><a href="' . esc_url($mapUrl) .
                    '" target="_blank" rel="noopener noreferrer">' .
                    esc_html__('Prikaži adresu na karti ↗', 'tvrtke-registar') .
                    '</a></p>';
            }
            $output .= '</aside></div><section class="tvrtke-detail-activities"><h3>' .
                esc_html__('Registrirane djelatnosti', 'tvrtke-registar') . '</h3><ul>';
            foreach (array_slice((array)($item['activities'] ?? []), 0, 100) as $row) {
                if (!is_array($row)) continue;
                $output .= '<li>' . esc_html((string)(($row['activity_text'] ?? null) ?: (($row['nkd_name'] ?? null) ?: (($row['nkd_code'] ?? null) ?: '—')))) . '</li>';
            }
            $output .= '</ul></section></div></div>';
            if (!empty($item['partial'])) $output .= self::partialNotice();
            return $output . '</section>';
        }
        if ($query === '' && $county === '' && $settlement === '' && $activity === '' && $status === '' && $legalForm === '' && $registryType === 'all') {
            return $output . '<p>' . esc_html__('Unesite kriterij za početak pretraživanja.', 'tvrtke-registar') . '</p></section>';
        }
        $data = self::request('/v1/directory', [
            'q' => $query, 'type' => $registryType, 'county' => $county, 'settlement' => $settlement, 'activity' => $activity, 'legal_form' => $legalForm, 'status' => $status,
            'page' => $page, 'limit' => $perPage,
        ]);
        if (is_wp_error($data)) return $output . '<p role="alert">' . esc_html($data->get_error_message()) . '</p></section>';
        if (!isset($data['items']) || !is_array($data['items'])) return $output . '<p role="alert">' . esc_html__('Neispravan odgovor API-ja.', 'tvrtke-registar') . '</p></section>';
        if (empty($data['snapshot_id']) && empty($data['sources'])) return $output . '<p role="status">' . esc_html__('Baza još nije sinkronizirana. Pretraga će biti dostupna nakon prvog uvoza.', 'tvrtke-registar') . '</p></section>';
        if (!empty($data['published_at_utc'])) {
            $output .= '<p class="tvrtke-source">' .
                esc_html__('Sudski registar, zadnja objava u bazi (UTC):', 'tvrtke-registar') .
                ' ' . esc_html((string)$data['published_at_utc']) . '</p>';
        }
        $output .= '<div class="tvrtke-results" aria-live="polite">';
        $output .= '<p class="tvrtke-count">' . esc_html(sprintf(/* translators: %d: Number of results on the current page. */ __('Rezultati na stranici: %d', 'tvrtke-registar'), count($data['items']))) . '</p>';
        if (empty($data['items'])) $output .= '<p class="tvrtke-empty">' . esc_html__('Nema rezultata u dostupnom skupu. Pokušajte s drugim kriterijima.', 'tvrtke-registar') . '</p>';
        foreach (array_slice($data['items'], 0, $perPage) as $row) {
            if (!is_array($row)) continue;
            $itemMbs = (string)($row['mbs'] ?? '');
            $key = (string)($row['registry_key'] ?? '');
            if (!preg_match('/^(sudreg|obrt|udruga|zaklada|ostalo):([a-f0-9]{64}|[0-9]{9})$/D', $key)) continue;
            $kind = (string)($row['source_type'] ?? 'ostalo');
            $typeLabel = $types[$kind] ?? __('Poslovni subjekt', 'tvrtke-registar');
            $statusNumber = $row['status'] ?? null;
            $statusClass = $statusNumber === null ? 'unknown' : ((string)$statusNumber === '1' ? 'active' : 'inactive');
            $icon = self::icon($kind);
            $url = add_query_arg(['tvrtke_ref' => $key], remove_query_arg(['tvrtke_mbs', 'tvrtke_ref', 'tvrtke_page']));
            $output .= '<article class="tvrtke-result" data-source="' . esc_attr($kind) . '">';
            $output .= '<span class="tvrtke-result-icon">' . $icon . '</span><div class="tvrtke-result-main">';
            $output .= '<h3><a href="' . esc_url($url) . '">' . esc_html((string)($row['name'] ?? $itemMbs)) . '</a></h3>';
            $output .= '<p class="tvrtke-result-meta"><span class="tvrtke-status tvrtke-status--' . esc_attr($statusClass) . '">' .
                esc_html((string)($row['status_label'] ?? __('Nepoznat', 'tvrtke-registar'))) . '</span>';
            if (in_array('settlement', $enabledFields, true) && !empty($row['settlement'])) {
                $output .= '<span>' . esc_html((string)$row['settlement']) . '</span>';
            }
            $output .= '</p><p class="tvrtke-result-numbers">' .
                esc_html__('Registracijski broj:', 'tvrtke-registar') . ' ' .
                esc_html((string)($row['source_identifier'] ?? $itemMbs)) . '</p>';
            if (in_array('oib', $enabledFields, true) && !empty($row['oib'])) {
                $output .= '<p class="tvrtke-result-numbers">' .
                    esc_html__('OIB:', 'tvrtke-registar') . ' ' . esc_html((string)$row['oib']) . '</p>';
            }
            if (in_array('activity', $enabledFields, true) && !empty($row['activity'])) {
                $output .= '<p class="tvrtke-result-activity">' .
                    esc_html((string)$row['activity']) . '</p>';
            }
            $output .= '</div>';
            $output .= '<div class="tvrtke-result-aside"><span class="tvrtke-result-type">' . esc_html($typeLabel) . '</span>';
            if (!empty($row['datum_osnivanja'])) $output .= '<p>' . esc_html__('Osnovano:', 'tvrtke-registar') . ' ' . esc_html((string)$row['datum_osnivanja']) . '</p>';
            if (!empty($row['datum_brisanja'])) $output .= '<p>' . esc_html__('Brisano:', 'tvrtke-registar') . ' ' . esc_html((string)$row['datum_brisanja']) . '</p>';
            if (!empty($row['datum_upisa'])) $output .= '<p>' . esc_html__('Upis:', 'tvrtke-registar') . ' ' . esc_html((string)$row['datum_upisa']) . '</p>';
            /* translators: %s: Name of the business or registered organization. */
            $detailAria = sprintf(__('Detalji: %s', 'tvrtke-registar'), (string)($row['name'] ?? ''));
            $output .= '</div><a class="tvrtke-detail-link" href="' . esc_url($url) . '" aria-label="' .
                esc_attr($detailAria) . '">' . esc_html__('Detalji →', 'tvrtke-registar') . '</a></article>';
        }
        $output .= '</div><nav class="tvrtke-pages" aria-label="' . esc_attr__('Stranice rezultata', 'tvrtke-registar') . '">';
        if ($page > 1) $output .= '<a href="' . esc_url(add_query_arg('tvrtke_page', $page - 1, remove_query_arg('tvrtke_mbs'))) . '">' . esc_html__('Prethodna', 'tvrtke-registar') . '</a>';
        if (!empty($data['has_more'])) $output .= '<a href="' . esc_url(add_query_arg('tvrtke_page', $page + 1, remove_query_arg('tvrtke_mbs'))) . '">' . esc_html__('Sljedeća', 'tvrtke-registar') . '</a>';
        $output .= '</nav>';
        if (!empty($data['partial'])) $output .= self::partialNotice();
        return $output . '<p class="tvrtke-source">' . esc_html__('Informativni podaci iz dostupnih javnih registara. Opseg baze može biti djelomičan.', 'tvrtke-registar') . '</p></section>';
    }

    private static function partialNotice(): string
    {
        return '<p class="tvrtke-warning" role="status">' . esc_html__('Prikazan je djelomično uvezen skup podataka. Rezultati ne obuhvaćaju nužno sve subjekte.', 'tvrtke-registar') . '</p>';
    }
}

Tvrtke_Registar::init();
