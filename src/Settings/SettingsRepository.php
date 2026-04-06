<?php

declare(strict_types=1);

namespace StoreLocator\Settings;

final class SettingsRepository
{
    public const OPTION_KEY = 'sl_settings';

    /// Defaults keep the plugin stable before frontend map rendering is introduced.
    private const DEFAULTS = [
        'marker_color'           => '#2e7d32',
        'marker_image_url'       => '',
        'marker_active_image_url'=> '',
        'default_zoom'           => '7',
        'default_center_lat'     => '47.0979',
        'default_center_lng'     => '19.5402',
        'search_button_text'          => '',
        'search_button_bg_color'      => '#f5821f',
        'search_button_text_color'    => '#ffffff',
        'search_button_border_radius' => '999',
        'search_button_border_width'  => '0',
        'search_button_border_color'  => '#f5821f',
        'search_button_typography_preset' => 'custom',
        'search_button_font_size'     => '30',
        'search_button_font_weight'   => '800',
        'search_button_letter_spacing'=> '1',
        'search_button_text_transform'=> 'uppercase',
        'search_input_text_color'     => '#374151',
        'search_input_placeholder_text' => '',
        'search_input_placeholder_color' => '#6b7280',
        'search_input_bg_color'       => '#ffffff',
        'search_input_border_radius'  => '999',
        'search_input_border_width'   => '2',
        'search_input_border_color'   => '#6b7280',
        'search_input_typography_preset' => 'custom',
        'search_input_font_size'      => '31',
        'search_input_font_weight'    => '400',
        'info_title_typography_preset'   => 'custom',
        'info_title_font_family'      => '',
        'info_title_font_size'        => '42',
        'info_title_font_weight'      => '800',
        'info_title_color'            => '#1f2937',
        'info_address_typography_preset' => 'custom',
        'info_address_font_family'    => '',
        'info_address_font_size'      => '20',
        'info_address_font_weight'    => '800',
        'info_address_color'          => '#111827',
        'info_phone_typography_preset'   => 'custom',
        'info_phone_font_family'      => '',
        'info_phone_font_size'        => '34',
        'info_phone_font_weight'      => '400',
        'info_phone_color'            => '#1f2937',
        'info_email_typography_preset'   => 'custom',
        'info_email_font_family'      => '',
        'info_email_font_size'        => '34',
        'info_email_font_weight'      => '400',
        'info_email_color'            => '#1f2937',
        'info_hours_typography_preset'   => 'custom',
        'info_hours_font_family'      => '',
        'info_hours_font_size'        => '13',
        'info_hours_font_weight'      => '600',
        'info_hours_color'            => '#111827',
    ];

    public function get_all(): array
    {
        $saved = get_option(self::OPTION_KEY, []);

        if (! is_array($saved)) {
            $saved = [];
        }

        $settings = wp_parse_args($saved, self::DEFAULTS);
        $settings['default_zoom'] = (int) self::DEFAULTS['default_zoom'];
        $settings['default_center_lat'] = (string) self::DEFAULTS['default_center_lat'];
        $settings['default_center_lng'] = (string) self::DEFAULTS['default_center_lng'];

        if (
            ! is_numeric((string) $settings['default_center_lat']) ||
            ! is_numeric((string) $settings['default_center_lng']) ||
            (
                (float) $settings['default_center_lat'] === 0.0 &&
                (float) $settings['default_center_lng'] === 0.0
            )
        ) {
            $settings['default_center_lat'] = (string) self::DEFAULTS['default_center_lat'];
            $settings['default_center_lng'] = (string) self::DEFAULTS['default_center_lng'];
        }

        return $settings;
    }

    public function get(string $key)
    {
        $settings = $this->get_all();

        return $settings[$key] ?? null;
    }

    public function get_defaults(): array
    {
        return self::DEFAULTS;
    }

    public function sanitize(array $raw_settings): array
    {
        $sanitized = $this->get_defaults();

        $marker_color = isset($raw_settings['marker_color']) ? sanitize_text_field((string) $raw_settings['marker_color']) : '';
        $sanitized['marker_color'] = $this->sanitize_color($marker_color);

        $marker_image_url = isset($raw_settings['marker_image_url']) ? esc_url_raw((string) $raw_settings['marker_image_url']) : '';
        $sanitized['marker_image_url'] = $marker_image_url;
        $marker_active_image_url = isset($raw_settings['marker_active_image_url']) ? esc_url_raw((string) $raw_settings['marker_active_image_url']) : '';
        $sanitized['marker_active_image_url'] = $marker_active_image_url;

        $sanitized['default_zoom'] = (int) self::DEFAULTS['default_zoom'];

        $sanitized['search_button_text'] = isset($raw_settings['search_button_text'])
            ? sanitize_text_field((string) $raw_settings['search_button_text'])
            : (string) self::DEFAULTS['search_button_text'];
        $sanitized['search_button_bg_color'] = $this->sanitize_color(
            isset($raw_settings['search_button_bg_color']) ? sanitize_text_field((string) $raw_settings['search_button_bg_color']) : (string) self::DEFAULTS['search_button_bg_color'],
            'search_button_bg_color'
        );
        $sanitized['search_button_text_color'] = $this->sanitize_color(
            isset($raw_settings['search_button_text_color']) ? sanitize_text_field((string) $raw_settings['search_button_text_color']) : (string) self::DEFAULTS['search_button_text_color'],
            'search_button_text_color'
        );
        $sanitized['search_button_border_radius'] = $this->sanitize_size(
            $raw_settings['search_button_border_radius'] ?? self::DEFAULTS['search_button_border_radius'],
            'search_button_border_radius'
        );
        $sanitized['search_button_border_width'] = $this->sanitize_size(
            $raw_settings['search_button_border_width'] ?? self::DEFAULTS['search_button_border_width'],
            'search_button_border_width'
        );
        $sanitized['search_button_border_color'] = $this->sanitize_color(
            isset($raw_settings['search_button_border_color']) ? sanitize_text_field((string) $raw_settings['search_button_border_color']) : (string) self::DEFAULTS['search_button_border_color'],
            'search_button_border_color'
        );
        $sanitized['search_button_typography_preset'] = $this->sanitize_typography_preset(
            isset($raw_settings['search_button_typography_preset']) ? sanitize_key((string) $raw_settings['search_button_typography_preset']) : (string) self::DEFAULTS['search_button_typography_preset']
        );
        $sanitized['search_button_font_size'] = $this->sanitize_size(
            $raw_settings['search_button_font_size'] ?? self::DEFAULTS['search_button_font_size'],
            'search_button_font_size'
        );
        $sanitized['search_button_font_weight'] = $this->sanitize_font_weight(
            $raw_settings['search_button_font_weight'] ?? self::DEFAULTS['search_button_font_weight'],
            'search_button_font_weight'
        );
        $sanitized['search_button_letter_spacing'] = $this->sanitize_size(
            $raw_settings['search_button_letter_spacing'] ?? self::DEFAULTS['search_button_letter_spacing'],
            'search_button_letter_spacing'
        );
        $sanitized['search_button_text_transform'] = $this->sanitize_text_transform(
            isset($raw_settings['search_button_text_transform']) ? sanitize_key((string) $raw_settings['search_button_text_transform']) : (string) self::DEFAULTS['search_button_text_transform']
        );
        $sanitized['search_input_text_color'] = $this->sanitize_color(
            isset($raw_settings['search_input_text_color']) ? sanitize_text_field((string) $raw_settings['search_input_text_color']) : (string) self::DEFAULTS['search_input_text_color'],
            'search_input_text_color'
        );
        $sanitized['search_input_placeholder_text'] = isset($raw_settings['search_input_placeholder_text'])
            ? sanitize_text_field((string) $raw_settings['search_input_placeholder_text'])
            : (string) self::DEFAULTS['search_input_placeholder_text'];
        $sanitized['search_input_placeholder_color'] = $this->sanitize_color(
            isset($raw_settings['search_input_placeholder_color']) ? sanitize_text_field((string) $raw_settings['search_input_placeholder_color']) : (string) self::DEFAULTS['search_input_placeholder_color'],
            'search_input_placeholder_color'
        );
        $sanitized['search_input_bg_color'] = $this->sanitize_color(
            isset($raw_settings['search_input_bg_color']) ? sanitize_text_field((string) $raw_settings['search_input_bg_color']) : (string) self::DEFAULTS['search_input_bg_color'],
            'search_input_bg_color'
        );
        $sanitized['search_input_border_radius'] = $this->sanitize_size(
            $raw_settings['search_input_border_radius'] ?? self::DEFAULTS['search_input_border_radius'],
            'search_input_border_radius'
        );
        $sanitized['search_input_border_width'] = $this->sanitize_size(
            $raw_settings['search_input_border_width'] ?? self::DEFAULTS['search_input_border_width'],
            'search_input_border_width'
        );
        $sanitized['search_input_border_color'] = $this->sanitize_color(
            isset($raw_settings['search_input_border_color']) ? sanitize_text_field((string) $raw_settings['search_input_border_color']) : (string) self::DEFAULTS['search_input_border_color'],
            'search_input_border_color'
        );
        $sanitized['search_input_typography_preset'] = $this->sanitize_typography_preset(
            isset($raw_settings['search_input_typography_preset']) ? sanitize_key((string) $raw_settings['search_input_typography_preset']) : (string) self::DEFAULTS['search_input_typography_preset']
        );
        $sanitized['search_input_font_size'] = $this->sanitize_size(
            $raw_settings['search_input_font_size'] ?? self::DEFAULTS['search_input_font_size'],
            'search_input_font_size'
        );
        $sanitized['search_input_font_weight'] = $this->sanitize_font_weight(
            $raw_settings['search_input_font_weight'] ?? self::DEFAULTS['search_input_font_weight'],
            'search_input_font_weight'
        );
        $sanitized['info_title_typography_preset'] = $this->sanitize_typography_preset(
            isset($raw_settings['info_title_typography_preset']) ? sanitize_key((string) $raw_settings['info_title_typography_preset']) : (string) self::DEFAULTS['info_title_typography_preset']
        );
        $sanitized['info_title_font_family'] = $this->sanitize_font_family($raw_settings['info_title_font_family'] ?? self::DEFAULTS['info_title_font_family']);
        $sanitized['info_title_font_size'] = $this->sanitize_size($raw_settings['info_title_font_size'] ?? self::DEFAULTS['info_title_font_size'], 'info_title_font_size');
        $sanitized['info_title_font_weight'] = $this->sanitize_font_weight($raw_settings['info_title_font_weight'] ?? self::DEFAULTS['info_title_font_weight'], 'info_title_font_weight');
        $sanitized['info_title_color'] = $this->sanitize_color(
            isset($raw_settings['info_title_color']) ? sanitize_text_field((string) $raw_settings['info_title_color']) : (string) self::DEFAULTS['info_title_color'],
            'info_title_color'
        );
        $sanitized['info_address_typography_preset'] = $this->sanitize_typography_preset(
            isset($raw_settings['info_address_typography_preset']) ? sanitize_key((string) $raw_settings['info_address_typography_preset']) : (string) self::DEFAULTS['info_address_typography_preset']
        );
        $sanitized['info_address_font_family'] = $this->sanitize_font_family($raw_settings['info_address_font_family'] ?? self::DEFAULTS['info_address_font_family']);
        $sanitized['info_address_font_size'] = $this->sanitize_size($raw_settings['info_address_font_size'] ?? self::DEFAULTS['info_address_font_size'], 'info_address_font_size');
        $sanitized['info_address_font_weight'] = $this->sanitize_font_weight($raw_settings['info_address_font_weight'] ?? self::DEFAULTS['info_address_font_weight'], 'info_address_font_weight');
        $sanitized['info_address_color'] = $this->sanitize_color(
            isset($raw_settings['info_address_color']) ? sanitize_text_field((string) $raw_settings['info_address_color']) : (string) self::DEFAULTS['info_address_color'],
            'info_address_color'
        );
        $sanitized['info_phone_typography_preset'] = $this->sanitize_typography_preset(
            isset($raw_settings['info_phone_typography_preset']) ? sanitize_key((string) $raw_settings['info_phone_typography_preset']) : (string) self::DEFAULTS['info_phone_typography_preset']
        );
        $sanitized['info_phone_font_family'] = $this->sanitize_font_family($raw_settings['info_phone_font_family'] ?? self::DEFAULTS['info_phone_font_family']);
        $sanitized['info_phone_font_size'] = $this->sanitize_size($raw_settings['info_phone_font_size'] ?? self::DEFAULTS['info_phone_font_size'], 'info_phone_font_size');
        $sanitized['info_phone_font_weight'] = $this->sanitize_font_weight($raw_settings['info_phone_font_weight'] ?? self::DEFAULTS['info_phone_font_weight'], 'info_phone_font_weight');
        $sanitized['info_phone_color'] = $this->sanitize_color(
            isset($raw_settings['info_phone_color']) ? sanitize_text_field((string) $raw_settings['info_phone_color']) : (string) self::DEFAULTS['info_phone_color'],
            'info_phone_color'
        );
        $sanitized['info_email_typography_preset'] = $this->sanitize_typography_preset(
            isset($raw_settings['info_email_typography_preset']) ? sanitize_key((string) $raw_settings['info_email_typography_preset']) : (string) self::DEFAULTS['info_email_typography_preset']
        );
        $sanitized['info_email_font_family'] = $this->sanitize_font_family($raw_settings['info_email_font_family'] ?? self::DEFAULTS['info_email_font_family']);
        $sanitized['info_email_font_size'] = $this->sanitize_size($raw_settings['info_email_font_size'] ?? self::DEFAULTS['info_email_font_size'], 'info_email_font_size');
        $sanitized['info_email_font_weight'] = $this->sanitize_font_weight($raw_settings['info_email_font_weight'] ?? self::DEFAULTS['info_email_font_weight'], 'info_email_font_weight');
        $sanitized['info_email_color'] = $this->sanitize_color(
            isset($raw_settings['info_email_color']) ? sanitize_text_field((string) $raw_settings['info_email_color']) : (string) self::DEFAULTS['info_email_color'],
            'info_email_color'
        );
        $sanitized['info_hours_typography_preset'] = $this->sanitize_typography_preset(
            isset($raw_settings['info_hours_typography_preset']) ? sanitize_key((string) $raw_settings['info_hours_typography_preset']) : (string) self::DEFAULTS['info_hours_typography_preset']
        );
        $sanitized['info_hours_font_family'] = $this->sanitize_font_family($raw_settings['info_hours_font_family'] ?? self::DEFAULTS['info_hours_font_family']);
        $sanitized['info_hours_font_size'] = $this->sanitize_size($raw_settings['info_hours_font_size'] ?? self::DEFAULTS['info_hours_font_size'], 'info_hours_font_size');
        $sanitized['info_hours_font_weight'] = $this->sanitize_font_weight($raw_settings['info_hours_font_weight'] ?? self::DEFAULTS['info_hours_font_weight'], 'info_hours_font_weight');
        $sanitized['info_hours_color'] = $this->sanitize_color(
            isset($raw_settings['info_hours_color']) ? sanitize_text_field((string) $raw_settings['info_hours_color']) : (string) self::DEFAULTS['info_hours_color'],
            'info_hours_color'
        );

        return $sanitized;
    }

    private function sanitize_color(string $value, string $key = 'marker_color'): string
    {
        $value = trim($value);
        $hex = sanitize_hex_color($value);

        if (is_string($hex) && $hex !== '') {
            return strtolower($hex);
        }

        return (string) (self::DEFAULTS[$key] ?? self::DEFAULTS['marker_color']);
    }

    private function sanitize_size($value, string $key): string
    {
        $number = is_numeric((string) $value) ? (int) $value : (int) self::DEFAULTS[$key];
        $number = max(0, min(999, $number));

        return (string) $number;
    }

    private function sanitize_font_weight($value, string $fallback_key): string
    {
        $number = is_numeric((string) $value) ? (int) $value : (int) self::DEFAULTS[$fallback_key];
        $allowed = [100, 200, 300, 400, 500, 600, 700, 800, 900];

        if (! in_array($number, $allowed, true)) {
            $number = 400;
        }

        return (string) $number;
    }

    private function sanitize_text_transform(string $value): string
    {
        $allowed = ['none', 'uppercase', 'lowercase', 'capitalize'];

        if (! in_array($value, $allowed, true)) {
            return (string) self::DEFAULTS['search_button_text_transform'];
        }

        return $value;
    }

    private function sanitize_font_family($value): string
    {
        return sanitize_text_field((string) $value);
    }

    private function sanitize_typography_preset(string $value): string
    {
        $allowed = ['custom', 'primary', 'secondary', 'text', 'accent'];

        if (! in_array($value, $allowed, true)) {
            return 'custom';
        }

        return $value;
    }

}
