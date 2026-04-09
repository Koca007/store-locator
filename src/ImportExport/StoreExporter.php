<?php

declare(strict_types=1);

namespace StoreLocator\ImportExport;

use StoreLocator\PostType\StorePostType;

final class StoreExporter
{
    private const WEBFY_COLUMNS = [
        'Cegnev',
        'Telephely cime',
        'Nyitvatartasi ido',
        'Telefonszam',
        'E-mail cim',
        'Termekkorok',
    ];

    private const WEEK_DAYS = [
        'monday'    => 'Hetfo',
        'tuesday'   => 'Kedd',
        'wednesday' => 'Szerda',
        'thursday'  => 'Csutortok',
        'friday'    => 'Pentek',
        'saturday'  => 'Szombat',
        'sunday'    => 'Vasarnap',
    ];

    public function stream_export_csv(): void
    {
        nocache_headers();

        $filename = 'store-locator-' . gmdate('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'wb');

        if ($output === false) {
            wp_die(esc_html__('Could not open export stream.', 'store-locator'));
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, self::WEBFY_COLUMNS, ';');

        foreach ($this->get_export_rows() as $row) {
            fputcsv($output, $row, ';');
        }

        fclose($output);
    }

    public function stream_sample_csv(): void
    {
        nocache_headers();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=store-locator-import-sample.csv');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'wb');

        if ($output === false) {
            wp_die(esc_html__('Could not open sample stream.', 'store-locator'));
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, self::WEBFY_COLUMNS, ';');
        fputcsv(
            $output,
            [
                'Sample Store',
                '1111 Budapest, Fo utca 1',
                "Hetfo: 07:00 - 16:00\nKedd: 07:00 - 16:00\nSzerda: 07:00 - 16:00\nCsutortok: 07:00 - 16:00\nPentek: 07:00 - 16:00\nSzombat: -\nVasarnap: -",
                '+36 30 123 4567',
                'hello@example.com',
                'Building materials, bathroom, doors/windows',
            ],
            ';'
        );

        fclose($output);
    }

    private function get_export_rows(): array
    {
        $posts = get_posts(
            [
                'post_type'              => StorePostType::POST_TYPE,
                'post_status'            => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page'         => -1,
                'orderby'                => 'title',
                'order'                  => 'ASC',
                'suppress_filters'       => true,
                'ignore_sticky_posts'    => true,
                'no_found_rows'          => true,
                'update_post_meta_cache' => true,
                'update_post_term_cache' => false,
            ]
        );

        $rows = [];

        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }

            $rows[] = [
                (string) get_the_title($post),
                $this->build_combined_address($post->ID),
                $this->build_multiline_opening_hours((string) get_post_meta($post->ID, '_sl_opening_hours', true)),
                (string) get_post_meta($post->ID, '_sl_phone', true),
                (string) get_post_meta($post->ID, '_sl_email', true),
                (string) get_post_meta($post->ID, '_sl_product_ranges', true),
            ];
        }

        return $rows;
    }

    private function build_combined_address(int $post_id): string
    {
        $address = trim((string) get_post_meta($post_id, '_sl_address', true));
        $zip = trim((string) get_post_meta($post_id, '_sl_zip', true));
        $city = trim((string) get_post_meta($post_id, '_sl_city', true));

        $city_line = trim($zip . ' ' . $city);

        if ($city_line !== '' && $address !== '') {
            return $city_line . ', ' . $address;
        }

        if ($city_line !== '') {
            return $city_line;
        }

        return $address;
    }

    private function build_multiline_opening_hours(string $raw_json): string
    {
        $decoded = json_decode($raw_json, true);

        if (! is_array($decoded)) {
            $decoded = [];
        }

        $lines = [];

        foreach (self::WEEK_DAYS as $day_key => $day_label) {
            $day_data = isset($decoded[$day_key]) && is_array($decoded[$day_key]) ? $decoded[$day_key] : [];
            $from = isset($day_data['from']) ? trim((string) $day_data['from']) : '';
            $to = isset($day_data['to']) ? trim((string) $day_data['to']) : '';
            $value = ($from !== '' && $to !== '') ? ($from . ' - ' . $to) : '-';

            $lines[] = $day_label . ': ' . $value;
        }

        return implode("\n", $lines);
    }
}
