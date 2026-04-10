<?php

declare(strict_types=1);

namespace StoreLocator\Geocoding;

use StoreLocator\PostType\StorePostType;

final class StoreGeocodingHandler
{
    private const META_ADDRESS = '_sl_address';
    private const META_ZIP = '_sl_zip';
    private const META_CITY = '_sl_city';
    private const META_LATITUDE = '_sl_latitude';
    private const META_LONGITUDE = '_sl_longitude';
    private const META_SOURCE_HASH = '_sl_geocode_source_hash';

    private GeocodingService $geocoding_service;

    public function __construct(GeocodingService $geocoding_service)
    {
        $this->geocoding_service = $geocoding_service;
    }

    public function register(): void
    {
        add_action('save_post_' . StorePostType::POST_TYPE, [$this, 'handle_store_save'], 20, 3);
    }

    public function handle_store_save(int $post_id, \WP_Post $post): void
    {
        if ($post->post_type !== StorePostType::POST_TYPE) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id)) {
            return;
        }

        if (! current_user_can('edit_post', $post_id)) {
            return;
        }

        $address = (string) get_post_meta($post_id, self::META_ADDRESS, true);
        $zip = (string) get_post_meta($post_id, self::META_ZIP, true);
        $city = (string) get_post_meta($post_id, self::META_CITY, true);

        $source_hash = $this->geocoding_service->build_source_hash($address, $zip, $city);
        $stored_source_hash = (string) get_post_meta($post_id, self::META_SOURCE_HASH, true);
        $stored_latitude = (string) get_post_meta($post_id, self::META_LATITUDE, true);
        $stored_longitude = (string) get_post_meta($post_id, self::META_LONGITUDE, true);

        if ($stored_latitude !== '' && $stored_longitude !== '') {
            if ($stored_source_hash !== $source_hash) {
                update_post_meta($post_id, self::META_SOURCE_HASH, $source_hash);
            }

            return;
        }

        $result = $this->geocoding_service->geocode_address($address, $zip, $city);

        if ($result === null) {
            return;
        }

        update_post_meta($post_id, self::META_LATITUDE, (string) $result->get_latitude());
        update_post_meta($post_id, self::META_LONGITUDE, (string) $result->get_longitude());
        update_post_meta($post_id, self::META_SOURCE_HASH, $source_hash);
    }
}
