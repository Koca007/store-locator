<?php

declare(strict_types=1);

namespace StoreLocator\ImportExport;

use StoreLocator\Geocoding\GeocodingService;
use StoreLocator\PostType\StorePostType;

final class StoreImporter
{
    private GeocodingService $geocoding_service;

    public function __construct(GeocodingService $geocoding_service)
    {
        $this->geocoding_service = $geocoding_service;
    }

    public function read_csv_rows(string $tmp_name): array
    {
        $rows = [];
        $handle = fopen($tmp_name, 'rb');

        if ($handle === false) {
            return $rows;
        }

        $first_line = fgets($handle);
        rewind($handle);

        $delimiter = $this->detect_delimiter($first_line);

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (! is_array($data)) {
                continue;
            }

            if (isset($data[0])) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/u', '', (string) $data[0]) ?? (string) $data[0];
            }

            $rows[] = array_map(
                static function ($value): string {
                    return is_string($value) ? trim($value) : '';
                },
                $data
            );
        }

        fclose($handle);

        return $rows;
    }

    public function map_rows(array $rows): array
    {
        $mapped = [];
        $header_map = [];
        $has_header = false;

        if (isset($rows[0]) && is_array($rows[0])) {
            $header_map = $this->build_header_map($rows[0]);
            $has_header = ! empty($header_map);
        }

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            if ($has_header && $index === 0) {
                continue;
            }

            $line_number = $index + 1;
            $normalized = $this->empty_row();

            if ($has_header) {
                foreach ($header_map as $column => $position) {
                    $normalized[$column] = isset($row[$position]) ? trim((string) $row[$position]) : '';
                }
            } else {
                foreach (StoreCsvSchema::COLUMNS as $position => $column) {
                    $normalized[$column] = isset($row[$position]) ? trim((string) $row[$position]) : '';
                }
            }

            if ($this->is_empty_row($normalized)) {
                continue;
            }

            $mapped[] = [
                'line' => $line_number,
                'row'  => $normalized,
            ];
        }

        return $mapped;
    }

    public function import(array $mapped_rows): array
    {
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($mapped_rows as $mapped_row) {
            $line = isset($mapped_row['line']) ? (int) $mapped_row['line'] : 0;
            $row = isset($mapped_row['row']) && is_array($mapped_row['row']) ? $mapped_row['row'] : [];

            $name = sanitize_text_field((string) ($row['name'] ?? ''));
            $address = sanitize_text_field((string) ($row['address'] ?? ''));
            $zip = sanitize_text_field((string) ($row['zip'] ?? ''));
            $city = sanitize_text_field((string) ($row['city'] ?? ''));
            $phone = sanitize_text_field((string) ($row['phone'] ?? ''));
            $email_raw = (string) ($row['email'] ?? '');
            $website_raw = (string) ($row['website'] ?? '');
            $opening_hours_raw = (string) ($row['opening_hours'] ?? '');
            $latitude_raw = trim((string) ($row['latitude'] ?? ''));
            $longitude_raw = trim((string) ($row['longitude'] ?? ''));
            $status = StoreCsvSchema::normalize_status((string) ($row['status'] ?? 'publish'));

            if ($name === '') {
                $errors[] = sprintf(__('Line %d: Missing required "name".', 'store-locator'), $line);
                continue;
            }

            $email = '';
            if ($email_raw !== '') {
                $email = sanitize_email($email_raw);

                if (! is_email($email)) {
                    $errors[] = sprintf(__('Line %d: Invalid email.', 'store-locator'), $line);
                    continue;
                }
            }

            $website = '';
            if ($website_raw !== '') {
                $website = esc_url_raw($website_raw);

                if ($website === '' || ! filter_var($website, FILTER_VALIDATE_URL)) {
                    $errors[] = sprintf(__('Line %d: Invalid website URL.', 'store-locator'), $line);
                    continue;
                }
            }

            $opening_hours_parsed = StoreCsvSchema::parse_opening_hours($opening_hours_raw);
            if ($opening_hours_parsed['error'] !== '') {
                $errors[] = sprintf(
                    __('Line %d: %s', 'store-locator'),
                    $line,
                    $opening_hours_parsed['error']
                );
                continue;
            }

            $latitude = '';
            $longitude = '';

            if ($latitude_raw !== '' || $longitude_raw !== '') {
                if (! is_numeric($latitude_raw) || ! is_numeric($longitude_raw)) {
                    $errors[] = sprintf(
                        __('Line %d: latitude and longitude must both be numeric or both empty.', 'store-locator'),
                        $line
                    );
                    continue;
                }

                $latitude = (string) (float) $latitude_raw;
                $longitude = (string) (float) $longitude_raw;
            }

            $existing_id = $this->find_existing_store_id($name);
            $post_data = [
                'post_type'   => StorePostType::POST_TYPE,
                'post_title'  => $name,
                'post_status' => $status,
            ];

            if ($existing_id > 0) {
                $post_data['ID'] = $existing_id;
                $post_id = wp_update_post($post_data, true);
            } else {
                $post_id = wp_insert_post($post_data, true);
            }

            if (is_wp_error($post_id)) {
                $errors[] = sprintf(
                    __('Line %d: Could not save store (%s).', 'store-locator'),
                    $line,
                    $post_id->get_error_message()
                );
                continue;
            }

            $post_id = (int) $post_id;

            $this->update_meta_value($post_id, '_sl_address', $address);
            $this->update_meta_value($post_id, '_sl_zip', $zip);
            $this->update_meta_value($post_id, '_sl_city', $city);
            $this->update_meta_value($post_id, '_sl_phone', $phone);
            $this->update_meta_value($post_id, '_sl_email', $email);
            $this->update_meta_value($post_id, '_sl_website', $website);
            $this->update_meta_value($post_id, '_sl_opening_hours', (string) $opening_hours_parsed['json']);
            $this->update_meta_value($post_id, '_sl_latitude', $latitude);
            $this->update_meta_value($post_id, '_sl_longitude', $longitude);

            $source_hash = $this->geocoding_service->build_source_hash($address, $zip, $city);
            $this->update_meta_value($post_id, '_sl_geocode_source_hash', $source_hash);

            if ($existing_id > 0) {
                $updated++;
            } else {
                $created++;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors'  => $errors,
        ];
    }

    private function detect_delimiter($first_line): string
    {
        if (! is_string($first_line) || $first_line === '') {
            return ',';
        }

        $semicolon_count = substr_count($first_line, ';');
        $comma_count = substr_count($first_line, ',');
        $tab_count = substr_count($first_line, "\t");

        if ($tab_count > $semicolon_count && $tab_count > $comma_count) {
            return "\t";
        }

        if ($semicolon_count > $comma_count) {
            return ';';
        }

        return ',';
    }

    private function build_header_map(array $header_row): array
    {
        $map = [];

        foreach ($header_row as $index => $value) {
            $column = sanitize_key((string) $value);

            if (! in_array($column, StoreCsvSchema::COLUMNS, true)) {
                continue;
            }

            $map[$column] = (int) $index;
        }

        return $map;
    }

    private function empty_row(): array
    {
        $row = [];

        foreach (StoreCsvSchema::COLUMNS as $column) {
            $row[$column] = '';
        }

        return $row;
    }

    private function is_empty_row(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function find_existing_store_id(string $name): int
    {
        $posts = get_posts(
            [
                'post_type'              => StorePostType::POST_TYPE,
                'post_status'            => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page'         => 1,
                'title'                  => $name,
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'suppress_filters'       => true,
                'ignore_sticky_posts'    => true,
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]
        );

        if (empty($posts) || ! isset($posts[0]) || ! $posts[0] instanceof \WP_Post) {
            return 0;
        }

        return (int) $posts[0]->ID;
    }

    private function update_meta_value(int $post_id, string $meta_key, string $value): void
    {
        if ($value === '') {
            delete_post_meta($post_id, $meta_key);
            return;
        }

        update_post_meta($post_id, $meta_key, $value);
    }
}

