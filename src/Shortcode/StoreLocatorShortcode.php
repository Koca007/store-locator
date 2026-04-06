<?php

declare(strict_types=1);

namespace StoreLocator\Shortcode;

use StoreLocator\Frontend\AssetManager;
use StoreLocator\Frontend\StoreDataProvider;
use StoreLocator\Settings\SettingsRepository;

final class StoreLocatorShortcode
{
    private const DEFAULT_GROUP = 'default';

    private AssetManager $asset_manager;

    private StoreDataProvider $store_data_provider;

    private SettingsRepository $settings_repository;

    public function __construct(
        AssetManager $asset_manager,
        StoreDataProvider $store_data_provider,
        SettingsRepository $settings_repository
    ) {
        $this->asset_manager = $asset_manager;
        $this->store_data_provider = $store_data_provider;
        $this->settings_repository = $settings_repository;
    }

    public function register(): void
    {
        add_shortcode('store_locator', [$this, 'render_legacy']);
        add_shortcode('store_locator_map', [$this, 'render_map']);
        add_shortcode('store_locator_search', [$this, 'render_search']);
    }

    public function render_legacy(array $atts = []): string
    {
        $atts = shortcode_atts(
            [
                'group' => self::DEFAULT_GROUP,
            ],
            $atts,
            'store_locator'
        );

        $group = sanitize_key((string) $atts['group']);

        return $this->render_search(['group' => $group]) . $this->render_map(['group' => $group]);
    }

    public function render_map(array $atts = []): string
    {
        $this->asset_manager->enqueue();

        $atts = shortcode_atts(
            [
                'group' => self::DEFAULT_GROUP,
            ],
            $atts,
            'store_locator_map'
        );

        $group = sanitize_key((string) $atts['group']);
        if ($group === '') {
            $group = self::DEFAULT_GROUP;
        }

        $settings = $this->settings_repository->get_all();
        $labels = $this->get_labels();
        $info_panel_style = $this->build_info_panel_style_attribute($settings);

        $payload = [
            'config' => [
                'defaultZoom'       => (int) $settings['default_zoom'],
                'centerLat'         => (float) $settings['default_center_lat'],
                'centerLng'         => (float) $settings['default_center_lng'],
                'markerColor'       => (string) $settings['marker_color'],
                'markerImage'       => (string) $settings['marker_image_url'],
                'markerActiveImage' => (string) $settings['marker_active_image_url'],
            ],
            'labels' => $labels,
            'stores' => $this->store_data_provider->get_stores(),
            'group'  => $group,
        ];

        $json = wp_json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (! is_string($json)) {
            $json = '{}';
        }

        ob_start();
        ?>
        <div class="sl-store-locator sl-store-locator--map" data-sl-group="<?php echo esc_attr($group); ?>" style="<?php echo esc_attr($info_panel_style); ?>">
            <div class="sl-store-locator__stage">
                <aside class="sl-store-locator__details" aria-live="polite">
                    <div class="sl-store-locator__details-content"></div>
                    <a href="#" class="sl-store-locator__details-gps" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($labels['open_in_maps']); ?>">
                        <img src="<?php echo esc_url(STORE_LOCATOR_URL . 'assets/img/utvonalterv.svg'); ?>" alt="" />
                    </a>
                </aside>
                <div class="sl-store-locator__map"></div>
            </div>
            <script type="application/json" class="sl-store-locator__data"><?php echo $json; ?></script>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public function render_search(array $atts = []): string
    {
        $this->asset_manager->enqueue();

        $atts = shortcode_atts(
            [
                'group' => self::DEFAULT_GROUP,
            ],
            $atts,
            'store_locator_search'
        );

        $group = sanitize_key((string) $atts['group']);
        if ($group === '') {
            $group = self::DEFAULT_GROUP;
        }

        $settings = $this->settings_repository->get_all();
        $labels = $this->get_labels();
        $button_text = (string) $settings['search_button_text'] !== '' ? (string) $settings['search_button_text'] : (string) $labels['filter_button'];
        $placeholder_text = (string) $settings['search_input_placeholder_text'] !== '' ? (string) $settings['search_input_placeholder_text'] : (string) $labels['query_placeholder'];
        $search_style = $this->build_search_style_attribute($settings);

        ob_start();
        ?>
        <div class="sl-store-locator sl-store-locator--search" data-sl-group="<?php echo esc_attr($group); ?>" style="<?php echo esc_attr($search_style); ?>">
            <div class="sl-store-locator__filters">
                <label>
                    <input type="text" class="sl-filter-query" placeholder="<?php echo esc_attr($placeholder_text); ?>" />
                </label>
                <button type="button" class="sl-filter-submit"><?php echo esc_html($button_text); ?></button>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function get_labels(): array
    {
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        $language = strtolower(substr((string) $locale, 0, 2));

        if ($language === 'hu') {
            return [
                'city'                    => "V\u{00E1}ros",
                'zip'                     => "Ir\u{00E1}ny\u{00ED}t\u{00F3}sz\u{00E1}m",
                'query_placeholder'       => "Telep\u{00FC}l\u{00E9}s neve vagy ir\u{00E1}ny\u{00ED}t\u{00F3}sz\u{00E1}m",
                'city_placeholder'        => 'Pl.: Budapest',
                'zip_placeholder'         => 'Pl.: 1051',
                'filter_button'           => "L\u{00C1}SSUK A K\u{00D6}ZELI KERESKED\u{00C9}SEKET!",
                'no_results'              => "Nincs tal\u{00E1}lat.",
                'no_map_data'             => "A t\u{00E9}rk\u{00E9}pen jelenleg nincs megjelen\u{00ED}thet\u{0151} \u{00FC}zlet.",
                'nearest_fallback_prefix' => "Nincs pontos tal\u{00E1}lat, a legk\u{00F6}zelebbi \u{00FC}zlet:",
                'close_details'           => "R\u{00E9}szletek bez\u{00E1}r\u{00E1}sa",
                'opening_hours'           => "Nyitvatart\u{00E1}s",
                'email'                   => 'Email',
                'phone'                   => 'Telefon',
                'open_in_maps'            => "Megnyit\u{00E1}s Google T\u{00E9}rk\u{00E9}pen",
                'week_days'               => [
                    'monday'    => "H\u{00E9}tf\u{0151}",
                    'tuesday'   => 'Kedd',
                    'wednesday' => 'Szerda',
                    'thursday'  => "Cs\u{00FC}t\u{00F6}rt\u{00F6}k",
                    'friday'    => "P\u{00E9}ntek",
                    'saturday'  => 'Szombat',
                    'sunday'    => "Vas\u{00E1}rnap",
                ],
            ];
        }

        if ($language === 'de') {
            return [
                'city'                    => 'Stadt',
                'zip'                     => 'Postleitzahl',
                'query_placeholder'       => 'Stadt oder Postleitzahl',
                'city_placeholder'        => 'z.B.: Berlin',
                'zip_placeholder'         => 'z.B.: 10115',
                'filter_button'           => 'NAHE GESCHAEFTE ANZEIGEN!',
                'no_results'              => 'Keine Treffer.',
                'no_map_data'             => 'Aktuell sind keine Standorte mit Koordinaten verfuegbar.',
                'nearest_fallback_prefix' => 'Kein exakter Treffer, naechster Standort:',
                'close_details'           => 'Details schliessen',
                'opening_hours'           => 'Oeffnungszeiten',
                'email'                   => 'Email',
                'phone'                   => 'Telefon',
                'open_in_maps'            => 'In Google Maps oeffnen',
                'week_days'               => [
                    'monday'    => 'Montag',
                    'tuesday'   => 'Dienstag',
                    'wednesday' => 'Mittwoch',
                    'thursday'  => 'Donnerstag',
                    'friday'    => 'Freitag',
                    'saturday'  => 'Samstag',
                    'sunday'    => 'Sonntag',
                ],
            ];
        }

        return [
            'city'                    => 'City',
            'zip'                     => 'ZIP Code',
            'query_placeholder'       => 'City name or ZIP code',
            'city_placeholder'        => 'e.g. New York',
            'zip_placeholder'         => 'e.g. 10001',
            'filter_button'           => 'SHOW NEARBY STORES!',
            'no_results'              => 'No results found.',
            'no_map_data'             => 'No stores with coordinates are available yet.',
            'nearest_fallback_prefix' => 'No exact match, nearest store:',
            'close_details'           => 'Close details',
            'opening_hours'           => 'Opening Hours',
            'email'                   => 'Email',
            'phone'                   => 'Phone',
            'open_in_maps'            => 'Open in Google Maps',
            'week_days'               => [
                'monday'    => 'Monday',
                'tuesday'   => 'Tuesday',
                'wednesday' => 'Wednesday',
                'thursday'  => 'Thursday',
                'friday'    => 'Friday',
                'saturday'  => 'Saturday',
                'sunday'    => 'Sunday',
            ],
        ];
    }

    private function build_search_style_attribute(array $settings): string
    {
        $button_typography = $this->build_typography_vars(
            (string) $settings['search_button_typography_preset'],
            [
                '--sl-search-btn-font-size:' . (int) $settings['search_button_font_size'] . 'px',
                '--sl-search-btn-font-weight:' . (int) $settings['search_button_font_weight'],
                '--sl-search-btn-letter-spacing:' . (int) $settings['search_button_letter_spacing'] . 'px',
                '--sl-search-btn-text-transform:' . (string) $settings['search_button_text_transform'],
            ],
            '--sl-search-btn'
        );
        $input_typography = $this->build_typography_vars(
            (string) $settings['search_input_typography_preset'],
            [
                '--sl-search-input-font-size:' . (int) $settings['search_input_font_size'] . 'px',
                '--sl-search-input-font-weight:' . (int) $settings['search_input_font_weight'],
            ],
            '--sl-search-input'
        );

        $style = [
            '--sl-search-btn-bg:' . (string) $settings['search_button_bg_color'],
            '--sl-search-btn-color:' . (string) $settings['search_button_text_color'],
            '--sl-search-btn-border-width:' . (int) $settings['search_button_border_width'] . 'px',
            '--sl-search-btn-border-color:' . (string) $settings['search_button_border_color'],
            '--sl-search-btn-radius:' . (int) $settings['search_button_border_radius'] . 'px',
            '--sl-search-input-color:' . (string) $settings['search_input_text_color'],
            '--sl-search-input-placeholder-color:' . (string) $settings['search_input_placeholder_color'],
            '--sl-search-input-bg:' . (string) $settings['search_input_bg_color'],
            '--sl-search-input-border-width:' . (int) $settings['search_input_border_width'] . 'px',
            '--sl-search-input-border-color:' . (string) $settings['search_input_border_color'],
            '--sl-search-input-radius:' . (int) $settings['search_input_border_radius'] . 'px',
        ];
        $style = array_merge($style, $button_typography, $input_typography);

        return implode(';', array_filter($style, 'strlen'));
    }

    private function build_info_panel_style_attribute(array $settings): string
    {
        $title_typography = $this->build_typography_vars(
            (string) $settings['info_title_typography_preset'],
            [
                '--sl-info-title-font-family:' . (string) $settings['info_title_font_family'],
                '--sl-info-title-font-size:' . (int) $settings['info_title_font_size'] . 'px',
                '--sl-info-title-font-weight:' . (int) $settings['info_title_font_weight'],
            ],
            '--sl-info-title'
        );
        $address_typography = $this->build_typography_vars(
            (string) $settings['info_address_typography_preset'],
            [
                '--sl-info-address-font-family:' . (string) $settings['info_address_font_family'],
                '--sl-info-address-font-size:' . (int) $settings['info_address_font_size'] . 'px',
                '--sl-info-address-font-weight:' . (int) $settings['info_address_font_weight'],
            ],
            '--sl-info-address'
        );
        $phone_typography = $this->build_typography_vars(
            (string) $settings['info_phone_typography_preset'],
            [
                '--sl-info-phone-font-family:' . (string) $settings['info_phone_font_family'],
                '--sl-info-phone-font-size:' . (int) $settings['info_phone_font_size'] . 'px',
                '--sl-info-phone-font-weight:' . (int) $settings['info_phone_font_weight'],
            ],
            '--sl-info-phone'
        );
        $email_typography = $this->build_typography_vars(
            (string) $settings['info_email_typography_preset'],
            [
                '--sl-info-email-font-family:' . (string) $settings['info_email_font_family'],
                '--sl-info-email-font-size:' . (int) $settings['info_email_font_size'] . 'px',
                '--sl-info-email-font-weight:' . (int) $settings['info_email_font_weight'],
            ],
            '--sl-info-email'
        );
        $hours_typography = $this->build_typography_vars(
            (string) $settings['info_hours_typography_preset'],
            [
                '--sl-info-hours-font-family:' . (string) $settings['info_hours_font_family'],
                '--sl-info-hours-font-size:' . (int) $settings['info_hours_font_size'] . 'px',
                '--sl-info-hours-font-weight:' . (int) $settings['info_hours_font_weight'],
            ],
            '--sl-info-hours'
        );

        $style = [
            '--sl-info-title-color:' . (string) $settings['info_title_color'],
            '--sl-info-address-color:' . (string) $settings['info_address_color'],
            '--sl-info-phone-color:' . (string) $settings['info_phone_color'],
            '--sl-info-email-color:' . (string) $settings['info_email_color'],
            '--sl-info-hours-color:' . (string) $settings['info_hours_color'],
        ];
        $style = array_merge(
            $style,
            $title_typography,
            $address_typography,
            $phone_typography,
            $email_typography,
            $hours_typography
        );

        return implode(';', array_filter($style, 'strlen'));
    }

    private function build_typography_vars(string $preset, array $custom_vars, string $var_prefix): array
    {
        $preset = strtolower(trim($preset));
        $allowed = ['custom', 'primary', 'secondary', 'text', 'accent'];

        if (! in_array($preset, $allowed, true)) {
            $preset = 'custom';
        }

        if ($preset === 'custom') {
            return $custom_vars;
        }

        return [
            $var_prefix . '-font-family:var(--e-global-typography-' . $preset . '-font-family)',
            $var_prefix . '-font-size:var(--e-global-typography-' . $preset . '-font-size)',
            $var_prefix . '-font-weight:var(--e-global-typography-' . $preset . '-font-weight)',
            $var_prefix . '-text-transform:var(--e-global-typography-' . $preset . '-text-transform)',
            $var_prefix . '-letter-spacing:var(--e-global-typography-' . $preset . '-letter-spacing)',
        ];
    }
}
