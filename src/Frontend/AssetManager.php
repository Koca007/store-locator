<?php

declare(strict_types=1);

namespace StoreLocator\Frontend;

final class AssetManager
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets(): void
    {
        $css_file = STORE_LOCATOR_PATH . 'assets/css/store-locator.css';
        $js_file = STORE_LOCATOR_PATH . 'assets/js/store-locator.js';
        $css_version = file_exists($css_file) ? (string) filemtime($css_file) : STORE_LOCATOR_VERSION;
        $js_version = file_exists($js_file) ? (string) filemtime($js_file) : STORE_LOCATOR_VERSION;

        wp_register_style(
            'sl-leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
            [],
            '1.9.4'
        );

        wp_register_style(
            'sl-leaflet-markercluster',
            'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css',
            ['sl-leaflet'],
            '1.5.3'
        );

        wp_register_style(
            'sl-leaflet-markercluster-default',
            'https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css',
            ['sl-leaflet-markercluster'],
            '1.5.3'
        );

        wp_register_script(
            'sl-leaflet',
            'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
            [],
            '1.9.4',
            true
        );

        wp_register_script(
            'sl-leaflet-markercluster',
            'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js',
            ['sl-leaflet'],
            '1.5.3',
            true
        );

        wp_register_style(
            'sl-store-locator',
            STORE_LOCATOR_URL . 'assets/css/store-locator.css',
            ['sl-leaflet'],
            $css_version
        );

        wp_register_script(
            'sl-store-locator',
            STORE_LOCATOR_URL . 'assets/js/store-locator.js',
            ['sl-leaflet'],
            $js_version,
            true
        );
    }

    public function enqueue(): void
    {
        wp_enqueue_style('sl-store-locator');
        wp_enqueue_script('sl-store-locator');
    }
}
