<?php

declare(strict_types=1);

namespace StoreLocator\ImportExport;

final class StoreCsvSchema
{
    public const COLUMNS = [
        'name',
        'address',
        'zip',
        'city',
        'phone',
        'email',
        'website',
        'opening_hours',
        'latitude',
        'longitude',
        'status',
        'product_ranges',
    ];

    public const WEEK_DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    private const WEEK_DAY_ALIASES = [
        'monday'    => 'monday',
        'mon'       => 'monday',
        'hetfo'     => 'monday',
        'tuesday'   => 'tuesday',
        'tue'       => 'tuesday',
        'kedd'      => 'tuesday',
        'wednesday' => 'wednesday',
        'wed'       => 'wednesday',
        'szerda'    => 'wednesday',
        'thursday'  => 'thursday',
        'thu'       => 'thursday',
        'csutortok' => 'thursday',
        'friday'    => 'friday',
        'fri'       => 'friday',
        'pentek'    => 'friday',
        'saturday'  => 'saturday',
        'sat'       => 'saturday',
        'szombat'   => 'saturday',
        'sunday'    => 'sunday',
        'sun'       => 'sunday',
        'vasarnap'  => 'sunday',
    ];

    public static function get_meta_map(): array
    {
        return [
            'address'       => '_sl_address',
            'zip'           => '_sl_zip',
            'city'          => '_sl_city',
            'phone'         => '_sl_phone',
            'email'         => '_sl_email',
            'website'       => '_sl_website',
            'opening_hours' => '_sl_opening_hours',
            'latitude'      => '_sl_latitude',
            'longitude'     => '_sl_longitude',
            'product_ranges'=> '_sl_product_ranges',
        ];
    }

    public static function normalize_status(string $raw_status): string
    {
        $status = strtolower(trim($raw_status));

        if (in_array($status, ['publish', 'draft', 'pending', 'private'], true)) {
            return $status;
        }

        return 'publish';
    }

    public static function serialize_opening_hours(string $opening_hours_json): string
    {
        if ($opening_hours_json === '') {
            return '';
        }

        $decoded = json_decode($opening_hours_json, true);
        if (! is_array($decoded)) {
            return '';
        }

        $parts = [];

        foreach (self::WEEK_DAYS as $day_key) {
            if (! isset($decoded[$day_key]) || ! is_array($decoded[$day_key])) {
                continue;
            }

            $from = isset($decoded[$day_key]['from']) ? trim((string) $decoded[$day_key]['from']) : '';
            $to = isset($decoded[$day_key]['to']) ? trim((string) $decoded[$day_key]['to']) : '';

            if ($from === '' || $to === '') {
                continue;
            }

            $parts[] = $day_key . '=' . $from . '-' . $to;
        }

        return implode('|', $parts);
    }

    public static function parse_opening_hours(string $raw_value): array
    {
        $raw_value = trim($raw_value);

        if ($raw_value === '') {
            return [
                'json'  => '',
                'error' => '',
            ];
        }

        $pairs = preg_split('/[\|\r\n;]+/u', $raw_value);
        if (! is_array($pairs)) {
            return [
                'json'  => '',
                'error' => __('Invalid opening_hours format.', 'store-locator'),
            ];
        }

        $parsed = [];

        foreach ($pairs as $pair_raw) {
            $pair = trim((string) $pair_raw);
            if ($pair === '') {
                continue;
            }

            if (! preg_match('/^(.+?)\s*[:=]\s*(.+)$/u', $pair, $matches)) {
                return [
                    'json'  => '',
                    'error' => __('Invalid opening_hours segment. Use day=HH:MM-HH:MM.', 'store-locator'),
                ];
            }

            $day = self::resolve_week_day($matches[1]);
            $time_range = trim($matches[2]);

            if ($day === '') {
                return [
                    'json'  => '',
                    'error' => sprintf(
                        __('Invalid opening_hours day: %s', 'store-locator'),
                        trim($matches[1])
                    ),
                ];
            }

            if (self::is_closed_time_value($time_range)) {
                continue;
            }

            if (! preg_match('/^([01]?\d|2[0-3])[:.]([0-5]\d)\s*[-–]\s*([01]?\d|2[0-3])[:.]([0-5]\d)$/u', $time_range, $time_matches)) {
                return [
                    'json'  => '',
                    'error' => sprintf(
                        __('Invalid opening_hours time range for %s.', 'store-locator'),
                        $day
                    ),
                ];
            }

            $parsed[$day] = [
                'from' => sprintf('%02d:%02d', (int) $time_matches[1], (int) $time_matches[2]),
                'to'   => sprintf('%02d:%02d', (int) $time_matches[3], (int) $time_matches[4]),
            ];
        }

        if (empty($parsed)) {
            return [
                'json'  => '',
                'error' => '',
            ];
        }

        $encoded = wp_json_encode($parsed);

        return [
            'json'  => is_string($encoded) ? $encoded : '',
            'error' => '',
        ];
    }

    private static function resolve_week_day(string $raw_day): string
    {
        $normalized = self::normalize_token($raw_day);

        if (isset(self::WEEK_DAY_ALIASES[$normalized])) {
            return self::WEEK_DAY_ALIASES[$normalized];
        }

        return in_array($normalized, self::WEEK_DAYS, true) ? $normalized : '';
    }

    private static function normalize_token(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        } elseif (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', '', $value);

        return is_string($value) ? $value : '';
    }

    private static function is_closed_time_value(string $value): bool
    {
        $trimmed = trim($value);

        if ($trimmed === '-' || $trimmed === '–') {
            return true;
        }

        $normalized = self::normalize_token($trimmed);

        return in_array($normalized, ['zarva', 'closed', 'off'], true);
    }
}
