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

        $pairs = preg_split('/\|/', $raw_value);
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

            if (! preg_match('/^([a-z]+)\s*[:=]\s*(.+)$/i', $pair, $matches)) {
                return [
                    'json'  => '',
                    'error' => __('Invalid opening_hours segment. Use day=HH:MM-HH:MM.', 'store-locator'),
                ];
            }

            $day = strtolower(trim($matches[1]));
            $time_range = trim($matches[2]);

            if (! in_array($day, self::WEEK_DAYS, true)) {
                return [
                    'json'  => '',
                    'error' => sprintf(
                        __('Invalid opening_hours day: %s', 'store-locator'),
                        $day
                    ),
                ];
            }

            if ($time_range === '-') {
                continue;
            }

            if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d\s*-\s*([01]\d|2[0-3]):[0-5]\d$/', $time_range)) {
                return [
                    'json'  => '',
                    'error' => sprintf(
                        __('Invalid opening_hours time range for %s.', 'store-locator'),
                        $day
                    ),
                ];
            }

            $times = preg_split('/\s*-\s*/', $time_range);
            if (! is_array($times) || count($times) !== 2) {
                return [
                    'json'  => '',
                    'error' => sprintf(
                        __('Invalid opening_hours time range for %s.', 'store-locator'),
                        $day
                    ),
                ];
            }

            $parsed[$day] = [
                'from' => trim($times[0]),
                'to'   => trim($times[1]),
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
}

