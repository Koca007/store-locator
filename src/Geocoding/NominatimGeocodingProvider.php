<?php

declare(strict_types=1);

namespace StoreLocator\Geocoding;

final class NominatimGeocodingProvider implements GeocodingProviderInterface
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';
    private const PHOTON_ENDPOINT = 'https://photon.komoot.io/api/';

    public function geocode(string $query): ?GeocodingResult
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        $context = $this->extract_query_context($query);
        $row = $this->find_best_matching_row($query, $context['zip'], $context['city']);

        if (! is_array($row) || ! isset($row['lat'], $row['lon'])) {
            return null;
        }

        $latitude = is_numeric($row['lat']) ? (float) $row['lat'] : null;
        $longitude = is_numeric($row['lon']) ? (float) $row['lon'] : null;

        if ($latitude === null || $longitude === null) {
            return null;
        }

        return new GeocodingResult($latitude, $longitude);
    }

    private function find_best_matching_row(string $query, string $expected_zip, string $expected_city): ?array
    {
        $rows = $this->request_rows($query, 5);
        $best = $this->pick_best_row($rows, $expected_zip, $expected_city);

        if ($best !== null) {
            return $best;
        }

        $rows = $this->request_photon_rows($query, 5);
        $best = $this->pick_best_row($rows, $expected_zip, $expected_city);

        if ($best !== null) {
            return $best;
        }

        foreach ($this->build_fallback_queries($expected_zip, $expected_city) as $fallback_query) {
            $rows = $this->request_rows($fallback_query, 3);
            $best = $this->pick_best_row($rows, $expected_zip, $expected_city);

            if ($best !== null) {
                return $best;
            }

            $rows = $this->request_photon_rows($fallback_query, 3);
            $best = $this->pick_best_row($rows, $expected_zip, $expected_city);

            if ($best !== null) {
                return $best;
            }
        }

        if ($expected_zip === '' && $expected_city === '' && isset($rows[0]) && is_array($rows[0])) {
            return $rows[0];
        }

        return null;
    }

    private function request_rows(string $query, int $limit): array
    {
        $url = add_query_arg(
            [
                'q'              => $query,
                'format'         => 'jsonv2',
                'addressdetails' => 1,
                'limit'          => max(1, $limit),
                'countrycodes'   => 'hu',
            ],
            self::ENDPOINT
        );

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 4,
                'headers' => [
                    'User-Agent' => $this->build_user_agent(),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return [];
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);

        if (! is_string($body) || $body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function request_photon_rows(string $query, int $limit): array
    {
        $url = add_query_arg(
            [
                'q'     => $query,
                'limit' => max(1, $limit),
                'lang'  => 'en',
            ],
            self::PHOTON_ENDPOINT
        );

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 4,
                'headers' => [
                    'User-Agent' => $this->build_user_agent(),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return [];
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);

        if (! is_string($body) || $body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        if (
            ! is_array($decoded) ||
            ! isset($decoded['features']) ||
            ! is_array($decoded['features'])
        ) {
            return [];
        }

        $rows = [];

        foreach ($decoded['features'] as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $geometry = isset($feature['geometry']) && is_array($feature['geometry']) ? $feature['geometry'] : [];
            $coordinates = isset($geometry['coordinates']) && is_array($geometry['coordinates']) ? $geometry['coordinates'] : [];

            if (! isset($coordinates[0], $coordinates[1])) {
                continue;
            }

            $longitude = $coordinates[0];
            $latitude = $coordinates[1];

            if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                continue;
            }

            $properties = isset($feature['properties']) && is_array($feature['properties']) ? $feature['properties'] : [];
            $country_code = isset($properties['countrycode']) ? strtolower((string) $properties['countrycode']) : '';

            if ($country_code !== '' && $country_code !== 'hu') {
                continue;
            }

            $city = '';

            foreach (['city', 'town', 'village', 'county', 'state_district'] as $key) {
                if (! isset($properties[$key])) {
                    continue;
                }

                $value = trim((string) $properties[$key]);

                if ($value !== '') {
                    $city = $value;
                    break;
                }
            }

            $rows[] = [
                'lat'     => (string) $latitude,
                'lon'     => (string) $longitude,
                'address' => [
                    'postcode' => isset($properties['postcode']) ? (string) $properties['postcode'] : '',
                    'city'     => $city,
                ],
            ];
        }

        return $rows;
    }

    private function pick_best_row(array $rows, string $expected_zip, string $expected_city): ?array
    {
        $best = null;
        $best_score = PHP_INT_MIN;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $score = $this->score_row($row, $expected_zip, $expected_city);

            if ($score > $best_score) {
                $best = $row;
                $best_score = $score;
            }
        }

        if ($best === null) {
            return null;
        }

        if (($expected_zip !== '' || $expected_city !== '') && $best_score <= 0) {
            return null;
        }

        return $best;
    }

    private function score_row(array $row, string $expected_zip, string $expected_city): int
    {
        if (
            ! isset($row['lat'], $row['lon']) ||
            ! is_numeric($row['lat']) ||
            ! is_numeric($row['lon'])
        ) {
            return PHP_INT_MIN;
        }

        $score = 1;
        $context_matches = 0;

        if ($expected_zip !== '') {
            $postcode = $this->extract_postcode($row);

            if ($postcode !== '' && $postcode !== $expected_zip) {
                return -100;
            }

            if ($postcode === $expected_zip) {
                $context_matches++;
                $score += 4;
            }
        }

        if ($expected_city !== '') {
            $expected_city = $this->normalize_for_compare($expected_city);
            $localities = $this->extract_localities($row);
            $city_matched = false;

            foreach ($localities as $locality) {
                $normalized_locality = $this->normalize_for_compare($locality);

                if ($normalized_locality === '') {
                    continue;
                }

                if (
                    $normalized_locality === $expected_city ||
                    strpos($normalized_locality, $expected_city . ' ') === 0 ||
                    strpos($expected_city, $normalized_locality . ' ') === 0
                ) {
                    $city_matched = true;
                    break;
                }
            }

            if (! $city_matched && ! empty($localities)) {
                return -100;
            }

            if ($city_matched) {
                $context_matches++;
                $score += 4;
            }
        }

        if (($expected_zip !== '' || $expected_city !== '') && $context_matches === 0) {
            return 0;
        }

        return $score;
    }

    private function extract_postcode(array $row): string
    {
        if (isset($row['address']) && is_array($row['address']) && isset($row['address']['postcode'])) {
            $postcode = (string) $row['address']['postcode'];

            if (preg_match('/\b(\d{4})\b/u', $postcode, $matches)) {
                return (string) $matches[1];
            }
        }

        return '';
    }

    private function extract_localities(array $row): array
    {
        if (! isset($row['address']) || ! is_array($row['address'])) {
            return [];
        }

        $address = $row['address'];
        $keys = ['city', 'town', 'village', 'municipality', 'hamlet'];
        $localities = [];

        foreach ($keys as $key) {
            if (! isset($address[$key])) {
                continue;
            }

            $value = trim((string) $address[$key]);

            if ($value === '') {
                continue;
            }

            $localities[] = $value;
        }

        return array_values(array_unique($localities));
    }

    private function build_fallback_queries(string $zip, string $city): array
    {
        $fallbacks = [];

        if ($zip !== '' && $city !== '') {
            $fallbacks[] = $zip . ', ' . $city . ', Hungary';
        }

        if ($city !== '') {
            $fallbacks[] = $city . ', Hungary';
        }

        return array_values(array_unique($fallbacks));
    }

    private function extract_query_context(string $query): array
    {
        $parts = array_values(
            array_filter(
                array_map('trim', explode(',', $query)),
                static function ($part): bool {
                    return $part !== '';
                }
            )
        );

        if (empty($parts)) {
            return [
                'zip'  => '',
                'city' => '',
            ];
        }

        $last_part = $parts[count($parts) - 1];

        if (in_array($this->normalize_for_compare($last_part), ['hungary', 'magyarorszag'], true)) {
            array_pop($parts);
        }

        $zip = '';
        $city = '';

        foreach ($parts as $index => $part) {
            if (! preg_match('/\b(\d{4})\b/u', $part, $matches)) {
                continue;
            }

            $zip = (string) $matches[1];
            $city = isset($parts[$index + 1]) ? trim((string) $parts[$index + 1]) : '';
            break;
        }

        if ($city === '' && ! empty($parts)) {
            $city = trim((string) $parts[count($parts) - 1]);
        }

        if (preg_match('/^\d{3,}$/', $city)) {
            $city = '';
        }

        return [
            'zip'  => $zip,
            'city' => $city,
        ];
    }

    private function normalize_for_compare(string $value): string
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

        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }

        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value);

        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private function build_user_agent(): string
    {
        $site_url = parse_url(home_url(), PHP_URL_HOST);

        if (! is_string($site_url) || $site_url === '') {
            $site_url = 'localhost';
        }

        return 'store-locator/' . STORE_LOCATOR_VERSION . ' (' . $site_url . ')';
    }
}
