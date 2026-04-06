<?php

declare(strict_types=1);

namespace StoreLocator\Admin;

use StoreLocator\ImportExport\StoreCsvSchema;
use StoreLocator\ImportExport\StoreExporter;
use StoreLocator\ImportExport\StoreImporter;
use StoreLocator\PostType\StorePostType;

final class ImportExportPage
{
    private const PAGE_SLUG = 'sl-import-export';
    private const IMPORT_NONCE_ACTION = 'sl_import_stores';
    private const EXPORT_NONCE_ACTION = 'sl_export_stores';
    private const SAMPLE_NONCE_ACTION = 'sl_sample_stores';
    private const NOTICE_TRANSIENT_PREFIX = 'sl_import_notice_';

    private StoreImporter $importer;
    private StoreExporter $exporter;

    public function __construct(StoreImporter $importer, StoreExporter $exporter)
    {
        $this->importer = $importer;
        $this->exporter = $exporter;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_sl_import_stores', [$this, 'handle_import']);
        add_action('admin_post_sl_export_stores', [$this, 'handle_export']);
        add_action('admin_post_sl_download_store_sample', [$this, 'handle_sample_download']);
    }

    public function register_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . StorePostType::POST_TYPE,
            __('Store Import / Export', 'store-locator'),
            __('Import / Export', 'store-locator'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $this->render_notice();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Store Import / Export', 'store-locator'); ?></h1>
            <p>
                <?php echo esc_html__('Supported format: CSV (Excel compatible). UTF-8 and semicolon/comma delimiters are supported.', 'store-locator'); ?>
            </p>
            <p>
                <?php echo esc_html__('Columns:', 'store-locator'); ?>
                <code><?php echo esc_html(implode(', ', StoreCsvSchema::COLUMNS)); ?></code>
            </p>
            <p>
                <?php echo esc_html__('opening_hours format example:', 'store-locator'); ?>
                <code>monday=07:00-16:00|tuesday=07:00-16:00|sunday=-</code>
            </p>
            <p>
                <a class="button button-secondary" href="<?php echo esc_url($this->sample_download_url()); ?>">
                    <?php echo esc_html__('Download sample CSV', 'store-locator'); ?>
                </a>
            </p>

            <hr />

            <h2><?php echo esc_html__('Import stores', 'store-locator'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="sl_import_stores" />
                <?php wp_nonce_field(self::IMPORT_NONCE_ACTION, '_sl_nonce'); ?>
                <input type="file" name="sl_import_file" accept=".csv,text/csv" required />
                <?php submit_button(__('Import CSV', 'store-locator'), 'primary', 'submit', false); ?>
            </form>

            <h2><?php echo esc_html__('Export stores', 'store-locator'); ?></h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="sl_export_stores" />
                <?php wp_nonce_field(self::EXPORT_NONCE_ACTION, '_sl_nonce'); ?>
                <?php submit_button(__('Export CSV', 'store-locator'), 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    public function handle_import(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'store-locator'));
        }

        check_admin_referer(self::IMPORT_NONCE_ACTION, '_sl_nonce');

        if (! isset($_FILES['sl_import_file']) || ! is_array($_FILES['sl_import_file'])) {
            $this->redirect_with_notice([
                'type'    => 'error',
                'message' => __('Import failed: no file uploaded.', 'store-locator'),
            ]);
        }

        $file = $_FILES['sl_import_file'];

        if (! isset($file['tmp_name'], $file['error'], $file['name'])) {
            $this->redirect_with_notice([
                'type'    => 'error',
                'message' => __('Import failed: invalid upload data.', 'store-locator'),
            ]);
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            $this->redirect_with_notice([
                'type'    => 'error',
                'message' => __('Import failed: upload error.', 'store-locator'),
            ]);
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

        if ($extension !== 'csv') {
            $this->redirect_with_notice([
                'type'    => 'error',
                'message' => __('Import failed: only CSV is supported in this version.', 'store-locator'),
            ]);
        }

        $rows = $this->importer->read_csv_rows((string) $file['tmp_name']);
        $mapped_rows = $this->importer->map_rows($rows);

        if (empty($mapped_rows)) {
            $this->redirect_with_notice([
                'type'    => 'error',
                'message' => __('Import failed: no valid rows found.', 'store-locator'),
            ]);
        }

        $result = $this->importer->import($mapped_rows);

        $created = isset($result['created']) ? (int) $result['created'] : 0;
        $updated = isset($result['updated']) ? (int) $result['updated'] : 0;
        $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : [];

        $message = sprintf(
            __('Import completed. Created: %1$d, Updated: %2$d.', 'store-locator'),
            $created,
            $updated
        );

        $type = empty($errors) ? 'success' : 'warning';

        if (! empty($errors)) {
            $message .= ' ' . sprintf(
                __('Rows with errors: %d.', 'store-locator'),
                count($errors)
            );
        }

        $this->redirect_with_notice([
            'type'    => $type,
            'message' => $message,
            'errors'  => array_slice($errors, 0, 15),
        ]);
    }

    public function handle_export(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'store-locator'));
        }

        check_admin_referer(self::EXPORT_NONCE_ACTION, '_sl_nonce');

        $this->exporter->stream_export_csv();
        exit;
    }

    public function handle_sample_download(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'store-locator'));
        }

        check_admin_referer(self::SAMPLE_NONCE_ACTION);

        $this->exporter->stream_sample_csv();
        exit;
    }

    private function render_notice(): void
    {
        $user_id = get_current_user_id();
        $key = self::NOTICE_TRANSIENT_PREFIX . $user_id;
        $notice = get_transient($key);

        if (! is_array($notice)) {
            return;
        }

        delete_transient($key);

        $type = isset($notice['type']) ? sanitize_key((string) $notice['type']) : 'success';
        $message = isset($notice['message']) ? (string) $notice['message'] : '';
        $errors = isset($notice['errors']) && is_array($notice['errors']) ? $notice['errors'] : [];

        if ($message === '') {
            return;
        }

        $class = 'notice notice-success';
        if ($type === 'error') {
            $class = 'notice notice-error';
        } elseif ($type === 'warning') {
            $class = 'notice notice-warning';
        }

        echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($message) . '</p>';

        if (! empty($errors)) {
            echo '<ul style="margin-left:18px;list-style:disc;">';
            foreach ($errors as $error) {
                echo '<li>' . esc_html((string) $error) . '</li>';
            }
            echo '</ul>';
        }

        echo '</div>';
    }

    private function redirect_with_notice(array $notice): void
    {
        $user_id = get_current_user_id();
        $key = self::NOTICE_TRANSIENT_PREFIX . $user_id;
        set_transient($key, $notice, 5 * MINUTE_IN_SECONDS);

        wp_safe_redirect($this->page_url());
        exit;
    }

    private function page_url(): string
    {
        return admin_url('edit.php?post_type=' . StorePostType::POST_TYPE . '&page=' . self::PAGE_SLUG);
    }

    private function sample_download_url(): string
    {
        return wp_nonce_url(
            admin_url('admin-post.php?action=sl_download_store_sample'),
            self::SAMPLE_NONCE_ACTION
        );
    }
}

