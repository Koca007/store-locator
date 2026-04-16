<?php

declare(strict_types=1);

namespace StoreLocator\ImportExport;

use StoreLocator\Geocoding\GeocodingService;
use StoreLocator\PostType\StorePostType;

final class StoreImporter
{
    private const META_SOURCE_HASH = '_sl_geocode_source_hash';

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

    public function read_xlsx_rows(string $tmp_name): array
    {
        $shared_strings_xml = $this->read_zip_entry($tmp_name, 'xl/sharedStrings.xml');
        $workbook_xml = $this->read_zip_entry($tmp_name, 'xl/workbook.xml');
        $rels_xml = $this->read_zip_entry($tmp_name, 'xl/_rels/workbook.xml.rels');
        $sheet_path = $this->resolve_first_xlsx_sheet_path($workbook_xml, $rels_xml);
        $sheet_xml = $this->read_zip_entry($tmp_name, $sheet_path);

        if (! is_string($sheet_xml) || trim($sheet_xml) === '') {
            return [];
        }

        $shared_strings = $this->read_xlsx_shared_strings((string) $shared_strings_xml);
        $xml = @simplexml_load_string($sheet_xml);

        if ($xml === false) {
            return [];
        }

        $row_nodes = $xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]');

        if (! is_array($row_nodes)) {
            return [];
        }

        $rows = [];

        foreach ($row_nodes as $row_node) {
            $row = [];
            $cells = $row_node->xpath('./*[local-name()="c"]');

            if (! is_array($cells)) {
                continue;
            }

            $fallback_col = 0;

            foreach ($cells as $cell) {
                $ref = (string) ($cell['r'] ?? '');

                if (preg_match('/^([A-Z]+)/', $ref, $matches)) {
                    $col_index = $this->xlsx_column_to_index($matches[1]);
                } else {
                    $col_index = $fallback_col;
                }

                if ($col_index < 0) {
                    $fallback_col++;
                    continue;
                }

                $row[$col_index] = trim($this->xlsx_cell_value($cell, $shared_strings));
                $fallback_col = $col_index + 1;
            }

            if (empty($row)) {
                continue;
            }

            ksort($row);
            $max_index = max(array_keys($row));
            $normalized_row = [];

            for ($i = 0; $i <= $max_index; $i++) {
                $normalized_row[] = isset($row[$i]) ? (string) $row[$i] : '';
            }

            $rows[] = $normalized_row;
        }

        return $rows;
    }

    public function map_rows(array $rows): array
    {
        $mapped = [];
        $header_map = [];
        $has_header = false;
        $last_name = '';

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

            $has_non_name_data = false;

            foreach ($normalized as $column => $value) {
                if ($column === 'name') {
                    continue;
                }

                if (trim((string) $value) !== '') {
                    $has_non_name_data = true;
                    break;
                }
            }

            if ($normalized['name'] === '' && $last_name !== '' && $has_non_name_data) {
                $normalized['name'] = $last_name;
            } elseif ($normalized['name'] !== '') {
                $last_name = $normalized['name'];
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
            $product_ranges = sanitize_text_field((string) ($row['product_ranges'] ?? ''));
            $status = StoreCsvSchema::normalize_status((string) ($row['status'] ?? 'publish'));
            $original_address = $address;

            $split_address = $this->split_combined_address($address);

            if (is_array($split_address)) {
                if ($zip === '') {
                    $zip = $split_address['zip'];
                }

                if ($city === '') {
                    $city = $split_address['city'];
                }

                if ($split_address['address'] !== '') {
                    $address = $split_address['address'];
                }
            }

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
            $coordinates_explicitly_provided = false;

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
                $coordinates_explicitly_provided = true;
            }

            $existing_id = $this->find_existing_store_id($name, $address, $zip, $city);

            if ($existing_id === 0 && $original_address !== $address) {
                $existing_id = $this->find_existing_store_id($name, $original_address, '', '');
            }

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
            if ($coordinates_explicitly_provided) {
                $this->update_meta_value($post_id, '_sl_latitude', $latitude);
                $this->update_meta_value($post_id, '_sl_longitude', $longitude);
            } else {
                $existing_latitude = (string) get_post_meta($post_id, '_sl_latitude', true);
                $existing_longitude = (string) get_post_meta($post_id, '_sl_longitude', true);

                if ($existing_latitude === '' || $existing_longitude === '') {
                    $result = $this->geocoding_service->geocode_address($address, $zip, $city);

                    if ($result !== null) {
                        $this->update_meta_value($post_id, '_sl_latitude', (string) $result->get_latitude());
                        $this->update_meta_value($post_id, '_sl_longitude', (string) $result->get_longitude());
                    }
                }
            }
            $this->update_meta_value($post_id, '_sl_product_ranges', $product_ranges);

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

    public function recalculate_coordinates(bool $overwrite_existing = true): array
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

        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }

            $post_id = (int) $post->ID;
            $name = (string) get_the_title($post);
            $address = (string) get_post_meta($post_id, '_sl_address', true);
            $zip = (string) get_post_meta($post_id, '_sl_zip', true);
            $city = (string) get_post_meta($post_id, '_sl_city', true);
            $stored_latitude = (string) get_post_meta($post_id, '_sl_latitude', true);
            $stored_longitude = (string) get_post_meta($post_id, '_sl_longitude', true);

            if (
                ! $overwrite_existing &&
                $stored_latitude !== '' &&
                is_numeric($stored_latitude) &&
                $stored_longitude !== '' &&
                is_numeric($stored_longitude)
            ) {
                $skipped++;
                continue;
            }

            if (trim($address . $zip . $city) === '') {
                $failed++;
                $errors[] = sprintf(
                    __('Store "%s": missing address context for geocoding.', 'store-locator'),
                    $name !== '' ? $name : ('#' . $post_id)
                );
                continue;
            }

            $result = $this->geocoding_service->geocode_address($address, $zip, $city);

            if ($result === null) {
                $failed++;
                $errors[] = sprintf(
                    __('Store "%s": geocoding failed.', 'store-locator'),
                    $name !== '' ? $name : ('#' . $post_id)
                );
                continue;
            }

            $latitude = (string) $result->get_latitude();
            $longitude = (string) $result->get_longitude();
            $source_hash = $this->geocoding_service->build_source_hash($address, $zip, $city);

            update_post_meta($post_id, '_sl_latitude', $latitude);
            update_post_meta($post_id, '_sl_longitude', $longitude);
            update_post_meta($post_id, self::META_SOURCE_HASH, $source_hash);

            $updated++;
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'failed'  => $failed,
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
        $header_aliases = $this->get_header_aliases();

        foreach ($header_row as $index => $value) {
            $normalized = $this->normalize_header_name((string) $value);

            if ($normalized === '') {
                continue;
            }

            $column = $normalized;

            if (! in_array($column, StoreCsvSchema::COLUMNS, true)) {
                if (! isset($header_aliases[$normalized])) {
                    continue;
                }

                $column = $header_aliases[$normalized];
            }

            if (isset($map[$column])) {
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

    private function get_header_aliases(): array
    {
        return [
            'store_name'        => 'name',
            'company'           => 'name',
            'company_name'      => 'name',
            'cegnev'            => 'name',
            'ceg_nev'           => 'name',
            'telephely_cime'    => 'address',
            'telephely_cim'     => 'address',
            'telephelycime'     => 'address',
            'telephelycim'      => 'address',
            'cim'               => 'address',
            'zip_code'          => 'zip',
            'postal_code'       => 'zip',
            'postcode'          => 'zip',
            'iranyitoszam'      => 'zip',
            'city_name'         => 'city',
            'varos'             => 'city',
            'telepules'         => 'city',
            'phone_number'      => 'phone',
            'telefon'           => 'phone',
            'telefon_szam'      => 'phone',
            'telefonszam'       => 'phone',
            'email_cim'         => 'email',
            'emailcim'          => 'email',
            'email_address'     => 'email',
            'e_mail'            => 'email',
            'e_mail_cim'        => 'email',
            'url'               => 'website',
            'weboldal'          => 'website',
            'openinghours'      => 'opening_hours',
            'nyitvatartas'      => 'opening_hours',
            'nyitvatartasi_ido' => 'opening_hours',
            'nyitvatartasiido'  => 'opening_hours',
            'lat'               => 'latitude',
            'szelesseg'         => 'latitude',
            'lng'               => 'longitude',
            'lon'               => 'longitude',
            'hosszusag'         => 'longitude',
            'statusz'           => 'status',
            'allapot'           => 'status',
            'product_range'     => 'product_ranges',
            'product_ranges'    => 'product_ranges',
            'productrange'      => 'product_ranges',
            'productranges'     => 'product_ranges',
            'termekkor'         => 'product_ranges',
            'termekkorok'       => 'product_ranges',
        ];
    }

    private function normalize_header_name(string $value): string
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
        $value = preg_replace('/[^a-z0-9]+/u', '_', $value);

        if (! is_string($value)) {
            return '';
        }

        return trim($value, '_');
    }

    private function split_combined_address(string $raw_address): ?array
    {
        $raw_address = trim($raw_address);

        if ($raw_address === '') {
            return null;
        }

        if (! preg_match('/^\s*(\d{4})\s+([^,]+)\s*,\s*(.+)\s*$/u', $raw_address, $matches)) {
            return null;
        }

        $zip = sanitize_text_field((string) $matches[1]);
        $city = sanitize_text_field((string) trim($matches[2]));
        $address = sanitize_text_field((string) trim($matches[3]));

        if ($zip === '' || $city === '') {
            return null;
        }

        return [
            'zip'     => $zip,
            'city'    => $city,
            'address' => $address,
        ];
    }

    private function find_existing_store_id(string $name, string $address, string $zip, string $city): int
    {
        if ($address !== '') {
            $meta_query = [
                [
                    'key'     => '_sl_address',
                    'value'   => $address,
                    'compare' => '=',
                ],
            ];

            if ($zip !== '') {
                $meta_query[] = [
                    'key'     => '_sl_zip',
                    'value'   => $zip,
                    'compare' => '=',
                ];
            }

            if ($city !== '') {
                $meta_query[] = [
                    'key'     => '_sl_city',
                    'value'   => $city,
                    'compare' => '=',
                ];
            }

            $posts = get_posts(
                [
                    'post_type'              => StorePostType::POST_TYPE,
                    'post_status'            => ['publish', 'draft', 'pending', 'private'],
                    'posts_per_page'         => 1,
                    'fields'                 => 'ids',
                    'orderby'                => 'ID',
                    'order'                  => 'ASC',
                    'meta_query'             => $meta_query,
                    'suppress_filters'       => true,
                    'ignore_sticky_posts'    => true,
                    'no_found_rows'          => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ]
            );

            if (! empty($posts) && isset($posts[0])) {
                return (int) $posts[0];
            }

            // When an address is present but no exact address match exists, do not fall back to
            // "name-only" matching, because chain/franchise imports often contain the same company
            // name with multiple different addresses. Falling back by name would overwrite one row
            // repeatedly and keep only the last address.
            return 0;
        }

        if ($name === '') {
            return 0;
        }

        $posts = get_posts(
            [
                'post_type'              => StorePostType::POST_TYPE,
                'post_status'            => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page'         => 2,
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

        // Ambiguous name-only matches should not update a random existing record.
        if (count($posts) > 1) {
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

    private function read_xlsx_shared_strings(string $shared_xml): array
    {
        if (trim($shared_xml) === '') {
            return [];
        }

        $xml = @simplexml_load_string($shared_xml);

        if ($xml === false) {
            return [];
        }

        $items = $xml->xpath('//*[local-name()="si"]');

        if (! is_array($items)) {
            return [];
        }

        $strings = [];

        foreach ($items as $item) {
            $text_nodes = $item->xpath('.//*[local-name()="t"]');

            if (! is_array($text_nodes)) {
                $strings[] = '';
                continue;
            }

            $text = '';

            foreach ($text_nodes as $text_node) {
                $text .= (string) $text_node;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private function resolve_first_xlsx_sheet_path($workbook_xml, $rels_xml): string
    {
        if (! is_string($workbook_xml) || ! is_string($rels_xml)) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = @simplexml_load_string($workbook_xml);
        $rels = @simplexml_load_string($rels_xml);

        if ($workbook === false || $rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $sheet_nodes = $workbook->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]');

        if (! is_array($sheet_nodes) || ! isset($sheet_nodes[0])) {
            return 'xl/worksheets/sheet1.xml';
        }

        $rel_id = (string) $sheet_nodes[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;

        if ($rel_id === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        $rel_nodes = $rels->xpath('//*[local-name()="Relationship"][@Id="' . $rel_id . '"]');

        if (! is_array($rel_nodes) || ! isset($rel_nodes[0])) {
            return 'xl/worksheets/sheet1.xml';
        }

        $target = (string) ($rel_nodes[0]['Target'] ?? '');

        if ($target === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        $target = ltrim(str_replace('\\', '/', $target), '/');

        if (strpos($target, 'xl/') === 0) {
            return $target;
        }

        return 'xl/' . $target;
    }

    private function xlsx_column_to_index(string $column): int
    {
        $column = strtoupper(trim($column));
        $length = strlen($column);
        $index = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = ord($column[$i]);

            if ($char < 65 || $char > 90) {
                return -1;
            }

            $index = ($index * 26) + ($char - 64);
        }

        return $index - 1;
    }

    private function xlsx_cell_value(\SimpleXMLElement $cell, array $shared_strings): string
    {
        $type = (string) ($cell['t'] ?? '');

        if ($type === 'inlineStr') {
            $text_nodes = $cell->xpath('./*[local-name()="is"]/*[local-name()="t"]');

            if (is_array($text_nodes) && isset($text_nodes[0])) {
                return (string) $text_nodes[0];
            }

            return '';
        }

        $value_nodes = $cell->xpath('./*[local-name()="v"]');
        $raw = (is_array($value_nodes) && isset($value_nodes[0])) ? (string) $value_nodes[0] : '';

        if ($type === 's') {
            $idx = (int) $raw;

            return isset($shared_strings[$idx]) ? (string) $shared_strings[$idx] : '';
        }

        return $raw;
    }

    private function read_zip_entry(string $archive_path, string $entry_path)
    {
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();

            if ($zip->open($archive_path) === true) {
                $content = $zip->getFromName($entry_path);
                $zip->close();

                if (is_string($content)) {
                    return $content;
                }
            }
        }

        if (! class_exists('PclZip')) {
            $pclzip_path = ABSPATH . 'wp-admin/includes/class-pclzip.php';

            if (file_exists($pclzip_path)) {
                require_once $pclzip_path;
            }
        }

        if (class_exists('PclZip')) {
            $archive = new \PclZip($archive_path);
            $result = $archive->extract(
                PCLZIP_OPT_BY_NAME,
                $entry_path,
                PCLZIP_OPT_EXTRACT_AS_STRING
            );

            if (is_array($result) && isset($result[0]['content']) && is_string($result[0]['content'])) {
                return $result[0]['content'];
            }
        }

        return '';
    }
}
