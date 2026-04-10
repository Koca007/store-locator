<?php

declare(strict_types=1);

namespace StoreLocator\Frontend;

use StoreLocator\Geocoding\GeocodingService;
use StoreLocator\PostType\StorePostType;

final class StoreDataProvider
{
    private const META_SOURCE_HASH = '_sl_geocode_source_hash';

    private GeocodingService $geocoding_service;

    public function __construct(GeocodingService $geocoding_service)
    {
        $this->geocoding_service = $geocoding_service;
    }

    public function get_stores(): array
    {
        $allow_runtime_geocoding = (bool) apply_filters('sl_enable_runtime_geocoding', false);

        $posts = get_posts(
            [
                'post_type'              => StorePostType::POST_TYPE,
                'post_status'            => 'publish',
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

        $stores = [];

        foreach ($posts as $post) {
            if (! $post instanceof \WP_Post) {
                continue;
            }

            $address = (string) get_post_meta($post->ID, '_sl_address', true);
            $zip = (string) get_post_meta($post->ID, '_sl_zip', true);
            $city = (string) get_post_meta($post->ID, '_sl_city', true);

            $latitude_raw = (string) get_post_meta($post->ID, '_sl_latitude', true);
            $longitude_raw = (string) get_post_meta($post->ID, '_sl_longitude', true);
            $stored_source_hash = (string) get_post_meta($post->ID, self::META_SOURCE_HASH, true);
            $current_source_hash = $this->geocoding_service->build_source_hash($address, $zip, $city);

            if (
                $allow_runtime_geocoding &&
                (
                    ($latitude_raw === '' || ! is_numeric($latitude_raw)) ||
                    ($longitude_raw === '' || ! is_numeric($longitude_raw)) ||
                    $stored_source_hash !== $current_source_hash
                )
            ) {
                $coordinates = $this->try_geocode_and_persist(
                    $post->ID,
                    $address,
                    $zip,
                    $city,
                    $current_source_hash
                );
                $latitude_raw = $coordinates['latitude'];
                $longitude_raw = $coordinates['longitude'];
            }

            $opening_hours_raw = (string) get_post_meta($post->ID, '_sl_opening_hours', true);
            $opening_hours = json_decode($opening_hours_raw, true);

            if (! is_array($opening_hours)) {
                $opening_hours = [];
            }

            $stores[] = [
                'id'            => (int) $post->ID,
                'name'          => get_the_title($post),
                'address'       => $address,
                'zip'           => $zip,
                'city'          => $city,
                'phone'         => (string) get_post_meta($post->ID, '_sl_phone', true),
                'email'         => (string) get_post_meta($post->ID, '_sl_email', true),
                'website'       => (string) get_post_meta($post->ID, '_sl_website', true),
                'product_ranges'=> (string) get_post_meta($post->ID, '_sl_product_ranges', true),
                'opening_hours' => $opening_hours,
                'latitude'      => $latitude_raw !== '' && is_numeric($latitude_raw) ? (float) $latitude_raw : null,
                'longitude'     => $longitude_raw !== '' && is_numeric($longitude_raw) ? (float) $longitude_raw : null,
            ];
        }

        return $stores;
    }

    private function try_geocode_and_persist(
        int $post_id,
        string $address,
        string $zip,
        string $city,
        string $source_hash
    ): array
    {
        $result = $this->geocoding_service->geocode_address($address, $zip, $city);

        if ($result === null) {
            return [
                'latitude'  => '',
                'longitude' => '',
            ];
        }

        $latitude = (string) $result->get_latitude();
        $longitude = (string) $result->get_longitude();

        update_post_meta($post_id, '_sl_latitude', $latitude);
        update_post_meta($post_id, '_sl_longitude', $longitude);
        update_post_meta($post_id, self::META_SOURCE_HASH, $source_hash);

        return [
            'latitude'  => $latitude,
            'longitude' => $longitude,
        ];
    }
}
