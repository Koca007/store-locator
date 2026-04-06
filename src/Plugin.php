<?php

declare(strict_types=1);

namespace StoreLocator;

use StoreLocator\Core\Loader;
use StoreLocator\PostType\StorePostType;

final class Plugin
{
    private static ?Plugin $instance = null;

    private string $plugin_file;

    private bool $booted = false;

    private function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;
    }

    public static function bootstrap(string $plugin_file): self
    {
        if (self::$instance === null) {
            self::$instance = new self($plugin_file);
            self::$instance->register_lifecycle_hooks();
            self::$instance->boot();
        }

        return self::$instance;
    }

    private function register_lifecycle_hooks(): void
    {
        register_activation_hook($this->plugin_file, [self::class, 'activate']);
        register_deactivation_hook($this->plugin_file, [self::class, 'deactivate']);
    }

    public static function activate(): void
    {
        $store_post_type = new StorePostType();
        $store_post_type->register_post_type();

        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    private function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $loader = new Loader();
        $loader->register();

        $this->booted = true;
    }
}
