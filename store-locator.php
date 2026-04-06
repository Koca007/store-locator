<?php
/**
 * Plugin Name: Store Locator
 * Description: Store locator plugin foundation built for incremental development.
 * Version:     1.0.3
 * Author:      Koca
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: store-locator
 * Domain Path: /languages
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('STORE_LOCATOR_VERSION', '1.0.3');
define('STORE_LOCATOR_FILE', __FILE__);
define('STORE_LOCATOR_PATH', plugin_dir_path(__FILE__));
define('STORE_LOCATOR_URL', plugin_dir_url(__FILE__));

if (! defined('STORE_LOCATOR_GITHUB_REPOSITORY')) {
    /// GitHub repository in owner/repository format for automatic update checks.
    define('STORE_LOCATOR_GITHUB_REPOSITORY', 'Koca007/store-locator');
}

if (! defined('STORE_LOCATOR_GITHUB_TOKEN')) {
    /// Optional GitHub token for private repositories.
    define('STORE_LOCATOR_GITHUB_TOKEN', '');
}

$autoload_file = STORE_LOCATOR_PATH . 'vendor/autoload.php';

if (is_readable($autoload_file)) {
    require_once $autoload_file;
} else {
    /// Translator note: %s points to the plugin directory path.
    add_action('admin_notices', static function (): void {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        $message = sprintf(
            esc_html__('Store Locator is missing dependencies. Run composer install in %s.', 'store-locator'),
            '<code>' . esc_html(STORE_LOCATOR_PATH) . '</code>'
        );

        echo '<div class="notice notice-error"><p>' . wp_kses_post($message) . '</p></div>';
    });

    return;
}

StoreLocator\Plugin::bootstrap(STORE_LOCATOR_FILE);
