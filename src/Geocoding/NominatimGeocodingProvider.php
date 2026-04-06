<?php

declare(strict_types=1);

namespace StoreLocator\Geocoding;

final class NominatimGeocodingProvider implements GeocodingProviderInterface
{
    private const ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    public function geocode(string $query): ?GeocodingResult
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        $url = add_query_arg(
            [
                'q'            => $query,
                'format'       => 'jsonv2',
                'limit'        => 1,
                'countrycodes' => 'hu',
            ],
            self::ENDPOINT
        );

        $response = wp_remote_get(
            $url,
            [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => $this->build_user_agent(),
                ],
            ]
        );

        if (is_wp_error($response)) {
            return null;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);

        if (! is_string($body) || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded) || ! isset($decoded[0]) || ! is_array($decoded[0])) {
            return null;
        }

        $row = $decoded[0];

        if (! isset($row['lat'], $row['lon'])) {
            return null;
        }

        $latitude = is_numeric($row['lat']) ? (float) $row['lat'] : null;
        $longitude = is_numeric($row['lon']) ? (float) $row['lon'] : null;

        if ($latitude === null || $longitude === null) {
            return null;
        }

        return new GeocodingResult($latitude, $longitude);
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
