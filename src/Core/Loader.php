<?php

declare(strict_types=1);

namespace StoreLocator\Core;

use StoreLocator\Admin\ImportExportPage;
use StoreLocator\Admin\SettingsPage;
use StoreLocator\Admin\SvgUploadSupport;
use StoreLocator\Frontend\AssetManager;
use StoreLocator\Frontend\StoreDataProvider;
use StoreLocator\Geocoding\GeocodingService;
use StoreLocator\Geocoding\NominatimGeocodingProvider;
use StoreLocator\Geocoding\StoreGeocodingHandler;
use StoreLocator\ImportExport\StoreExporter;
use StoreLocator\ImportExport\StoreImporter;
use StoreLocator\Meta\StoreMetaBoxes;
use StoreLocator\PostType\StorePostType;
use StoreLocator\Settings\SettingsRepository;
use StoreLocator\Shortcode\StoreLocatorShortcode;
use StoreLocator\Update\GitHubUpdater;

final class Loader
{
    public function register(): void
    {
        add_action('init', [$this, 'load_textdomain']);
        add_action('plugins_loaded', [$this, 'on_plugins_loaded']);
    }
    
    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'store-locator',
            false,
            dirname(plugin_basename(STORE_LOCATOR_FILE)) . '/languages'
        );
    }

    public function on_plugins_loaded(): void
    {
        $store_post_type = new StorePostType();
        $store_post_type->register();

        $store_meta_boxes = new StoreMetaBoxes();
        $store_meta_boxes->register();

        $geocoding_provider = new NominatimGeocodingProvider();
        $geocoding_service = new GeocodingService($geocoding_provider);
        $store_geocoding_handler = new StoreGeocodingHandler($geocoding_service);
        $store_geocoding_handler->register();

        $settings_repository = new SettingsRepository();
        $settings_page = new SettingsPage($settings_repository);
        $settings_page->register();

        $store_importer = new StoreImporter($geocoding_service);
        $store_exporter = new StoreExporter();
        $import_export_page = new ImportExportPage($store_importer, $store_exporter);
        $import_export_page->register();

        $svg_upload_support = new SvgUploadSupport();
        $svg_upload_support->register();

        $asset_manager = new AssetManager();
        $asset_manager->register();

        $store_data_provider = new StoreDataProvider($geocoding_service);
        $store_locator_shortcode = new StoreLocatorShortcode(
            $asset_manager,
            $store_data_provider,
            $settings_repository
        );
        $store_locator_shortcode->register();

        $github_updater = new GitHubUpdater(
            STORE_LOCATOR_FILE,
            defined('STORE_LOCATOR_GITHUB_REPOSITORY') ? (string) STORE_LOCATOR_GITHUB_REPOSITORY : '',
            defined('STORE_LOCATOR_GITHUB_TOKEN') ? (string) STORE_LOCATOR_GITHUB_TOKEN : ''
        );
        $github_updater->register();
    }
}
