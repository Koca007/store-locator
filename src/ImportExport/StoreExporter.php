<?php

declare(strict_types=1);

namespace StoreLocator\ImportExport;

use StoreLocator\PostType\StorePostType;

final class StoreExporter
{
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
        fputcsv($output, StoreCsvSchema::COLUMNS, ';');

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
        fputcsv($output, StoreCsvSchema::COLUMNS, ';');
        fputcsv(
            $output,
            [
                'Sample Store',
                'Fo utca 1',
                '1111',
                'Budapest',
                '+36 30 123 4567',
                'hello@example.com',
                'https://example.com',
                'monday=07:00-16:00|tuesday=07:00-16:00|wednesday=07:00-16:00',
                '',
                '',
                'publish',
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
                (string) get_post_meta($post->ID, '_sl_address', true),
                (string) get_post_meta($post->ID, '_sl_zip', true),
                (string) get_post_meta($post->ID, '_sl_city', true),
                (string) get_post_meta($post->ID, '_sl_phone', true),
                (string) get_post_meta($post->ID, '_sl_email', true),
                (string) get_post_meta($post->ID, '_sl_website', true),
                StoreCsvSchema::serialize_opening_hours((string) get_post_meta($post->ID, '_sl_opening_hours', true)),
                (string) get_post_meta($post->ID, '_sl_latitude', true),
                (string) get_post_meta($post->ID, '_sl_longitude', true),
                (string) $post->post_status,
            ];
        }

        return $rows;
    }
}

