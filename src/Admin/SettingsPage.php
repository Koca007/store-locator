<?php

declare(strict_types=1);

namespace StoreLocator\Admin;

use StoreLocator\PostType\StorePostType;
use StoreLocator\Settings\SettingsRepository;

final class SettingsPage
{
    private const PAGE_SLUG = 'sl-settings';
    private const SETTINGS_GROUP = 'sl_settings_group';
    private const SECTION_GENERAL = 'sl_section_general';
    private const SECTION_SEARCH_INPUT_UI = 'sl_section_search_input_ui';
    private const SECTION_SEARCH_BUTTON_UI = 'sl_section_search_button_ui';
    private const SECTION_INFO_PANEL_UI = 'sl_section_info_panel_ui';

    private SettingsRepository $settings_repository;

    public function __construct(SettingsRepository $settings_repository)
    {
        $this->settings_repository = $settings_repository;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_menu(): void
    {
        $labels = $this->get_labels();

        add_submenu_page(
            'edit.php?post_type=' . StorePostType::POST_TYPE,
            $labels['page_title'],
            $labels['menu_title'],
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function register_settings(): void
    {
        register_setting(
            self::SETTINGS_GROUP,
            SettingsRepository::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default'           => $this->settings_repository->get_defaults(),
            ]
        );

        add_settings_section(
            self::SECTION_GENERAL,
            $this->get_labels()['section_general_title'],
            [$this, 'render_general_section_description'],
            self::PAGE_SLUG
        );

        add_settings_field(
            'marker_color',
            $this->get_labels()['marker_color'],
            [$this, 'render_marker_color_field'],
            self::PAGE_SLUG,
            self::SECTION_GENERAL
        );

        add_settings_field(
            'marker_image_url',
            $this->get_labels()['marker_image_url'],
            [$this, 'render_marker_image_field'],
            self::PAGE_SLUG,
            self::SECTION_GENERAL
        );

        add_settings_field(
            'marker_active_image_url',
            $this->get_labels()['marker_active_image_url'],
            [$this, 'render_marker_active_image_field'],
            self::PAGE_SLUG,
            self::SECTION_GENERAL
        );

        add_settings_field(
            'marker_preview',
            $this->get_labels()['marker_preview'],
            [$this, 'render_marker_preview_field'],
            self::PAGE_SLUG,
            self::SECTION_GENERAL
        );

        add_settings_section(
            self::SECTION_SEARCH_INPUT_UI,
            $this->get_labels()['section_search_input_ui_title'],
            [$this, 'render_search_input_ui_section_description'],
            self::PAGE_SLUG
        );

        add_settings_section(
            self::SECTION_SEARCH_BUTTON_UI,
            $this->get_labels()['section_search_button_ui_title'],
            [$this, 'render_search_button_ui_section_description'],
            self::PAGE_SLUG
        );

        add_settings_section(
            self::SECTION_INFO_PANEL_UI,
            $this->get_labels()['section_info_panel_ui_title'],
            [$this, 'render_info_panel_ui_section_description'],
            self::PAGE_SLUG
        );

        add_settings_field(
            'search_button_text',
            $this->get_labels()['search_button_text'],
            [$this, 'render_search_button_text_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_BUTTON_UI
        );

        add_settings_field(
            'search_button_bg_color',
            $this->get_labels()['search_button_bg_color'],
            [$this, 'render_search_button_bg_color_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_BUTTON_UI
        );

        add_settings_field(
            'search_button_text_color',
            $this->get_labels()['search_button_text_color'],
            [$this, 'render_search_button_text_color_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_BUTTON_UI
        );

        add_settings_field(
            'search_button_border_radius',
            $this->get_labels()['search_button_border_radius'],
            [$this, 'render_search_button_border_radius_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_BUTTON_UI
        );

        add_settings_field(
            'search_button_border_width',
            $this->get_labels()['search_button_border_width'],
            [$this, 'render_search_button_border_width_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_BUTTON_UI
        );

        add_settings_field(
            'search_button_border_color',
            $this->get_labels()['search_button_border_color'],
            [$this, 'render_search_button_border_color_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_BUTTON_UI
        );
        add_settings_field(
            'search_button_typography_preset',
            $this->get_labels()['typography_source'],
            [$this, 'render_search_button_typography_preset_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_BUTTON_UI
        );

        add_settings_field(
            'search_button_font_size',
            $this->get_labels()['search_button_font_size'],
            [$this, 'render_search_button_font_size_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_BUTTON_UI
        );

        add_settings_field(
            'search_button_font_weight',
            $this->get_labels()['search_button_font_weight'],
            [$this, 'render_search_button_font_weight_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_BUTTON_UI
        );

        add_settings_field(
            'search_button_letter_spacing',
            $this->get_labels()['search_button_letter_spacing'],
            [$this, 'render_search_button_letter_spacing_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_BUTTON_UI
        );

        add_settings_field(
            'search_button_text_transform',
            $this->get_labels()['search_button_text_transform'],
            [$this, 'render_search_button_text_transform_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_BUTTON_UI
        );

        add_settings_field(
            'search_input_text_color',
            $this->get_labels()['search_input_text_color'],
            [$this, 'render_search_input_text_color_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_INPUT_UI
        );

        add_settings_field(
            'search_input_placeholder_text',
            $this->get_labels()['search_input_placeholder_text'],
            [$this, 'render_search_input_placeholder_text_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_INPUT_UI
        );

        add_settings_field(
            'search_input_placeholder_color',
            $this->get_labels()['search_input_placeholder_color'],
            [$this, 'render_search_input_placeholder_color_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_INPUT_UI
        );

        add_settings_field(
            'search_input_bg_color',
            $this->get_labels()['search_input_bg_color'],
            [$this, 'render_search_input_bg_color_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_INPUT_UI
        );

        add_settings_field(
            'search_input_border_radius',
            $this->get_labels()['search_input_border_radius'],
            [$this, 'render_search_input_border_radius_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_INPUT_UI
        );

        add_settings_field(
            'search_input_border_width',
            $this->get_labels()['search_input_border_width'],
            [$this, 'render_search_input_border_width_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_INPUT_UI
        );

        add_settings_field(
            'search_input_border_color',
            $this->get_labels()['search_input_border_color'],
            [$this, 'render_search_input_border_color_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_INPUT_UI
        );
        add_settings_field(
            'search_input_typography_preset',
            $this->get_labels()['typography_source'],
            [$this, 'render_search_input_typography_preset_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_INPUT_UI
        );

        add_settings_field(
            'search_input_font_size',
            $this->get_labels()['search_input_font_size'],
            [$this, 'render_search_input_font_size_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_INPUT_UI
        );

        add_settings_field(
            'search_input_font_weight',
            $this->get_labels()['search_input_font_weight'],
            [$this, 'render_search_input_font_weight_field'],
            self::PAGE_SLUG,
            self::SECTION_SEARCH_INPUT_UI
        );

        $this->register_info_panel_fields();

    }

    private function register_info_panel_fields(): void
    {
        $labels = $this->get_labels();
        $fields = [
            'info_title' => $labels['info_group_title'],
            'info_address' => $labels['info_group_address'],
            'info_phone' => $labels['info_group_phone'],
            'info_email' => $labels['info_group_email'],
            'info_hours' => $labels['info_group_hours'],
        ];

        foreach ($fields as $prefix => $group_label) {
            add_settings_field(
                $prefix . '_typography_preset',
                $group_label . ' - ' . $labels['typography_source'],
                [$this, 'render_custom_field'],
                self::PAGE_SLUG,
                self::SECTION_INFO_PANEL_UI,
                [
                    'type' => 'typography_preset',
                    'key' => $prefix . '_typography_preset',
                    'help' => $labels['typography_source_help'],
                ]
            );

            add_settings_field(
                $prefix . '_font_family',
                $group_label . ' - ' . $labels['field_font_family'],
                [$this, 'render_custom_field'],
                self::PAGE_SLUG,
                self::SECTION_INFO_PANEL_UI,
                [
                    'type' => 'text',
                    'key' => $prefix . '_font_family',
                    'class_name' => 'regular-text',
                    'help' => $labels['help_font_family'],
                ]
            );

            add_settings_field(
                $prefix . '_font_size',
                $group_label . ' - ' . $labels['field_font_size'],
                [$this, 'render_custom_field'],
                self::PAGE_SLUG,
                self::SECTION_INFO_PANEL_UI,
                [
                    'type' => 'number',
                    'key' => $prefix . '_font_size',
                    'help' => $labels['help_font_size'],
                ]
            );

            add_settings_field(
                $prefix . '_font_weight',
                $group_label . ' - ' . $labels['field_font_weight'],
                [$this, 'render_custom_field'],
                self::PAGE_SLUG,
                self::SECTION_INFO_PANEL_UI,
                [
                    'type' => 'font_weight',
                    'key' => $prefix . '_font_weight',
                    'help' => $labels['help_font_weight'],
                ]
            );

            add_settings_field(
                $prefix . '_color',
                $group_label . ' - ' . $labels['field_color'],
                [$this, 'render_custom_field'],
                self::PAGE_SLUG,
                self::SECTION_INFO_PANEL_UI,
                [
                    'type' => 'color',
                    'key' => $prefix . '_color',
                    'default_color' => '#111827',
                    'help' => $labels['help_color'],
                ]
            );
        }
    }

    public function sanitize_settings(array $raw_settings): array
    {
        return $this->settings_repository->sanitize($raw_settings);
    }

    public function enqueue_assets(): void
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if ($page !== self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style(
            'sl-admin-settings-style',
            STORE_LOCATOR_URL . 'assets/css/admin-settings.css',
            [],
            STORE_LOCATOR_VERSION
        );

        wp_enqueue_script(
            'sl-admin-settings',
            STORE_LOCATOR_URL . 'assets/js/admin-settings.js',
            ['jquery', 'wp-color-picker'],
            STORE_LOCATOR_VERSION,
            true
        );

        wp_localize_script(
            'sl-admin-settings',
            'slAdminSettings',
            [
                'title'         => $this->get_labels()['media_title'],
                'buttonText'    => $this->get_labels()['media_button'],
                'svgTitle'      => $this->get_labels()['svg_media_title'],
                'svgButtonText' => $this->get_labels()['svg_media_button'],
                'svgOnlyError'  => $this->get_labels()['svg_only_error'],
                'typographySelectedPrefix' => $this->get_labels()['typography_selected_prefix'],
                'typographyOptions' => [
                    'custom' => $this->get_labels()['typography_custom'],
                    'primary' => $this->get_labels()['typography_primary'],
                    'secondary' => $this->get_labels()['typography_secondary'],
                    'text' => $this->get_labels()['typography_text'],
                    'accent' => $this->get_labels()['typography_accent'],
                ],
            ]
        );
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $labels = $this->get_labels();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html($labels['page_title']); ?></h1>
            <h2 class="nav-tab-wrapper sl-settings-tabs">
                <button type="button" class="nav-tab nav-tab-active" data-sl-tab="map"><?php echo esc_html($labels['tab_map_settings']); ?></button>
                <button type="button" class="nav-tab" data-sl-tab="search-input"><?php echo esc_html($labels['tab_search_input_settings']); ?></button>
                <button type="button" class="nav-tab" data-sl-tab="search-button"><?php echo esc_html($labels['tab_search_button_settings']); ?></button>
                <button type="button" class="nav-tab" data-sl-tab="info-panel"><?php echo esc_html($labels['tab_info_panel_settings']); ?></button>
            </h2>
            <form action="options.php" method="post">
                <?php
                settings_fields(self::SETTINGS_GROUP);
                ?>
                <div class="sl-settings-panel is-active" data-sl-tab-panel="map">
                    <?php $this->render_section(self::SECTION_GENERAL); ?>
                </div>
                <div class="sl-settings-panel" data-sl-tab-panel="search-input">
                    <?php $this->render_section(self::SECTION_SEARCH_INPUT_UI); ?>
                </div>
                <div class="sl-settings-panel" data-sl-tab-panel="search-button">
                    <?php $this->render_section(self::SECTION_SEARCH_BUTTON_UI); ?>
                </div>
                <div class="sl-settings-panel" data-sl-tab-panel="info-panel">
                    <?php $this->render_section(self::SECTION_INFO_PANEL_UI); ?>
                </div>
                <?php
                submit_button($labels['save_changes']);
                ?>
            </form>
        </div>
        <?php
    }

    public function render_general_section_description(): void
    {
        echo '<p>' . esc_html($this->get_labels()['section_general_description']) . '</p>';
    }

    public function render_search_input_ui_section_description(): void
    {
        echo '<p>' . esc_html($this->get_labels()['section_search_input_ui_description']) . '</p>';
    }

    public function render_search_button_ui_section_description(): void
    {
        echo '<p>' . esc_html($this->get_labels()['section_search_button_ui_description']) . '</p>';
    }

    public function render_info_panel_ui_section_description(): void
    {
        echo '<p>' . esc_html($this->get_labels()['section_info_panel_ui_description']) . '</p>';
    }

    private function render_section(string $section_id): void
    {
        global $wp_settings_sections, $wp_settings_fields;

        if (
            ! isset($wp_settings_sections[self::PAGE_SLUG]) ||
            ! isset($wp_settings_sections[self::PAGE_SLUG][$section_id])
        ) {
            return;
        }

        $section = $wp_settings_sections[self::PAGE_SLUG][$section_id];

        if (! empty($section['title'])) {
            echo '<h2>' . esc_html((string) $section['title']) . '</h2>';
        }

        if (isset($section['callback']) && is_callable($section['callback'])) {
            call_user_func($section['callback'], $section);
        }

        if (
            isset($wp_settings_fields[self::PAGE_SLUG]) &&
            isset($wp_settings_fields[self::PAGE_SLUG][$section_id])
        ) {
            echo '<table class="form-table" role="presentation">';
            do_settings_fields(self::PAGE_SLUG, $section_id);
            echo '</table>';
        }
    }

    public function render_marker_color_field(): void
    {
        $value = (string) $this->settings_repository->get('marker_color');
        echo '<input type="text" class="sl-color-field" name="' . esc_attr(SettingsRepository::OPTION_KEY) . '[marker_color]" value="' . esc_attr($value) . '" data-default-color="#2e7d32" />';
        echo '<p class="description">' . esc_html($this->get_labels()['marker_color_help']) . '</p>';
    }

    public function render_marker_image_field(): void
    {
        $value = (string) $this->settings_repository->get('marker_image_url');
        $labels = $this->get_labels();

        echo '<input type="url" class="regular-text sl-marker-image-url" name="' . esc_attr(SettingsRepository::OPTION_KEY) . '[marker_image_url]" value="' . esc_attr($value) . '" />';
        echo ' <button type="button" class="button sl-marker-image-select">' . esc_html($labels['select_image']) . '</button>';
        echo ' <button type="button" class="button sl-marker-svg-select">' . esc_html($labels['select_svg']) . '</button>';
        echo ' <button type="button" class="button sl-marker-image-remove">' . esc_html($labels['remove_image']) . '</button>';

        if ($value !== '') {
            echo '<div style="margin-top:8px;"><img src="' . esc_url($value) . '" alt="" class="sl-marker-image-preview" style="max-width:64px;height:auto;" /></div>';
        } else {
            echo '<div style="margin-top:8px;"><img src="" alt="" class="sl-marker-image-preview" style="display:none;max-width:64px;height:auto;" /></div>';
        }

        echo '<p class="description">' . esc_html($this->get_labels()['marker_image_url_help']) . '</p>';
    }

    public function render_marker_active_image_field(): void
    {
        $value = (string) $this->settings_repository->get('marker_active_image_url');
        $labels = $this->get_labels();

        echo '<input type="url" class="regular-text sl-marker-active-image-url" name="' . esc_attr(SettingsRepository::OPTION_KEY) . '[marker_active_image_url]" value="' . esc_attr($value) . '" />';
        echo ' <button type="button" class="button sl-marker-active-image-select">' . esc_html($labels['select_active_image']) . '</button>';
        echo ' <button type="button" class="button sl-marker-active-svg-select">' . esc_html($labels['select_active_svg']) . '</button>';
        echo ' <button type="button" class="button sl-marker-active-image-remove">' . esc_html($labels['remove_active_image']) . '</button>';

        if ($value !== '') {
            echo '<div style="margin-top:8px;"><img src="' . esc_url($value) . '" alt="" class="sl-marker-active-image-preview" style="max-width:64px;height:auto;" /></div>';
        } else {
            echo '<div style="margin-top:8px;"><img src="" alt="" class="sl-marker-active-image-preview" style="display:none;max-width:64px;height:auto;" /></div>';
        }

        echo '<p class="description">' . esc_html($this->get_labels()['marker_active_image_url_help']) . '</p>';
    }

    public function render_marker_preview_field(): void
    {
        $labels = $this->get_labels();
        $color = (string) $this->settings_repository->get('marker_color');
        $image_url = (string) $this->settings_repository->get('marker_image_url');
        $active_image_url = (string) $this->settings_repository->get('marker_active_image_url');
        $show_image = $image_url !== '';
        $show_active_image = $active_image_url !== '';

        echo '<div class="sl-live-preview" style="display:inline-flex;align-items:center;justify-content:center;gap:18px;width:260px;height:130px;border:1px solid #dcdcde;border-radius:8px;background:linear-gradient(180deg,#f8f9fb 0%,#edf2f7 100%);">';
        echo '<div class="sl-live-marker-wrap" style="position:relative;width:40px;height:52px;">';
        echo '<img src="' . esc_url($image_url) . '" alt="" class="sl-live-marker-preview-image" style="' . ($show_image ? '' : 'display:none;') . 'width:40px;height:52px;object-fit:contain;" />';
        echo '<span class="sl-live-marker-preview-pin" style="' . ($show_image ? 'display:none;' : '') . 'display:inline-block;width:24px;height:24px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);background:' . esc_attr($color) . ';position:absolute;left:8px;top:12px;"></span>';
        echo '<span class="sl-live-marker-preview-pin-inner" style="' . ($show_image ? 'display:none;' : '') . 'display:inline-block;width:10px;height:10px;border-radius:50%;background:#ffffff;position:absolute;left:15px;top:19px;"></span>';
        echo '</div>';
        echo '<div class="sl-live-marker-wrap sl-live-marker-wrap-active" style="position:relative;width:40px;height:52px;">';
        echo '<img src="' . esc_url($active_image_url) . '" alt="" class="sl-live-marker-active-preview-image" style="' . ($show_active_image ? '' : 'display:none;') . 'width:40px;height:52px;object-fit:contain;" />';
        echo '<span class="sl-live-marker-active-fallback" style="' . ($show_active_image ? 'display:none;' : '') . 'display:inline-block;width:24px;height:24px;border-radius:50%;background:' . esc_attr($color) . ';position:absolute;left:8px;top:14px;border:2px solid #111827;box-shadow:0 0 0 3px rgba(17,24,39,0.15);"></span>';
        echo '</div>';
        echo '</div>';
        echo '<p class="description">' . esc_html($labels['marker_preview_help']) . '</p>';
    }

    public function render_search_button_text_field(): void
    {
        $this->render_text_field('search_button_text', 'regular-text', $this->get_labels()['search_button_text_help']);
    }

    public function render_search_button_bg_color_field(): void
    {
        $this->render_color_field('search_button_bg_color', '#f5821f', $this->get_labels()['search_button_bg_color_help']);
    }

    public function render_search_button_text_color_field(): void
    {
        $this->render_color_field('search_button_text_color', '#ffffff', $this->get_labels()['search_button_text_color_help']);
    }

    public function render_search_button_border_radius_field(): void
    {
        $this->render_number_field('search_button_border_radius', $this->get_labels()['search_button_border_radius_help']);
    }

    public function render_search_button_border_width_field(): void
    {
        $this->render_number_field('search_button_border_width', $this->get_labels()['search_button_border_width_help']);
    }

    public function render_search_button_border_color_field(): void
    {
        $this->render_color_field('search_button_border_color', '#f5821f', $this->get_labels()['search_button_border_color_help']);
    }

    public function render_search_button_typography_preset_field(): void
    {
        $this->render_typography_preset_field('search_button_typography_preset', $this->get_labels()['typography_source_help']);
    }

    public function render_search_button_font_size_field(): void
    {
        $this->render_number_field('search_button_font_size', $this->get_labels()['search_button_font_size_help']);
    }

    public function render_search_button_font_weight_field(): void
    {
        $this->render_font_weight_field('search_button_font_weight', $this->get_labels()['search_button_font_weight_help']);
    }

    public function render_search_button_letter_spacing_field(): void
    {
        $this->render_number_field('search_button_letter_spacing', $this->get_labels()['search_button_letter_spacing_help']);
    }

    public function render_search_button_text_transform_field(): void
    {
        $this->render_text_transform_field('search_button_text_transform', $this->get_labels()['search_button_text_transform_help']);
    }

    public function render_search_input_text_color_field(): void
    {
        $this->render_color_field('search_input_text_color', '#374151', $this->get_labels()['search_input_text_color_help']);
    }

    public function render_search_input_placeholder_text_field(): void
    {
        $this->render_text_field('search_input_placeholder_text', 'regular-text', $this->get_labels()['search_input_placeholder_text_help']);
    }

    public function render_search_input_placeholder_color_field(): void
    {
        $this->render_color_field('search_input_placeholder_color', '#6b7280', $this->get_labels()['search_input_placeholder_color_help']);
    }

    public function render_search_input_bg_color_field(): void
    {
        $this->render_color_field('search_input_bg_color', '#ffffff', $this->get_labels()['search_input_bg_color_help']);
    }

    public function render_search_input_border_radius_field(): void
    {
        $this->render_number_field('search_input_border_radius', $this->get_labels()['search_input_border_radius_help']);
    }

    public function render_search_input_border_width_field(): void
    {
        $this->render_number_field('search_input_border_width', $this->get_labels()['search_input_border_width_help']);
    }

    public function render_search_input_border_color_field(): void
    {
        $this->render_color_field('search_input_border_color', '#6b7280', $this->get_labels()['search_input_border_color_help']);
    }

    public function render_search_input_typography_preset_field(): void
    {
        $this->render_typography_preset_field('search_input_typography_preset', $this->get_labels()['typography_source_help']);
    }

    public function render_search_input_font_size_field(): void
    {
        $this->render_number_field('search_input_font_size', $this->get_labels()['search_input_font_size_help']);
    }

    public function render_search_input_font_weight_field(): void
    {
        $this->render_font_weight_field('search_input_font_weight', $this->get_labels()['search_input_font_weight_help']);
    }

    private function render_text_field(string $key, string $class_name, string $help_text): void
    {
        $value = (string) $this->settings_repository->get($key);
        echo '<input type="text" class="' . esc_attr($class_name) . '" name="' . esc_attr(SettingsRepository::OPTION_KEY) . '[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" />';
        echo '<p class="description">' . esc_html($help_text) . '</p>';
    }

    private function render_color_field(string $key, string $default_color, string $help_text): void
    {
        $value = (string) $this->settings_repository->get($key);
        echo '<input type="text" class="sl-color-field" name="' . esc_attr(SettingsRepository::OPTION_KEY) . '[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" data-default-color="' . esc_attr($default_color) . '" />';
        echo '<p class="description">' . esc_html($help_text) . '</p>';
    }

    private function render_number_field(string $key, string $help_text): void
    {
        $value = (string) $this->settings_repository->get($key);
        echo '<input type="number" min="0" max="999" class="small-text" name="' . esc_attr(SettingsRepository::OPTION_KEY) . '[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" /> <span>px</span>';
        echo '<p class="description">' . esc_html($help_text) . '</p>';
    }

    private function render_font_weight_field(string $key, string $help_text): void
    {
        $value = (string) $this->settings_repository->get($key);
        $options = ['100', '200', '300', '400', '500', '600', '700', '800', '900'];

        echo '<select name="' . esc_attr(SettingsRepository::OPTION_KEY) . '[' . esc_attr($key) . ']">';
        foreach ($options as $option) {
            echo '<option value="' . esc_attr($option) . '"' . selected($value, $option, false) . '>' . esc_html($option) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html($help_text) . '</p>';
    }

    private function render_text_transform_field(string $key, string $help_text): void
    {
        $value = (string) $this->settings_repository->get($key);
        $options = [
            'none' => 'none',
            'uppercase' => 'uppercase',
            'lowercase' => 'lowercase',
            'capitalize' => 'capitalize',
        ];

        echo '<select name="' . esc_attr(SettingsRepository::OPTION_KEY) . '[' . esc_attr($key) . ']">';
        foreach ($options as $option_value => $label) {
            echo '<option value="' . esc_attr($option_value) . '"' . selected($value, $option_value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html($help_text) . '</p>';
    }

    private function render_typography_preset_field(string $key, string $help_text): void
    {
        $value = (string) $this->settings_repository->get($key);
        $labels = $this->get_labels();
        $options = [
            'custom' => $labels['typography_custom'],
            'primary' => $labels['typography_primary'],
            'secondary' => $labels['typography_secondary'],
            'text' => $labels['typography_text'],
            'accent' => $labels['typography_accent'],
        ];

        echo '<select class="sl-typography-preset-select" name="' . esc_attr(SettingsRepository::OPTION_KEY) . '[' . esc_attr($key) . ']" data-setting-key="' . esc_attr($key) . '">';
        foreach ($options as $option_value => $label) {
            echo '<option value="' . esc_attr($option_value) . '"' . selected($value, $option_value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description sl-typography-preset-note" data-setting-key="' . esc_attr($key) . '"></p>';
        echo '<p class="description">' . esc_html($help_text) . '</p>';
    }

    public function render_custom_field(array $args): void
    {
        $type = isset($args['type']) ? (string) $args['type'] : 'text';
        $key = isset($args['key']) ? (string) $args['key'] : '';
        $help = isset($args['help']) ? (string) $args['help'] : '';

        if ($key === '') {
            return;
        }

        if ($type === 'color') {
            $default_color = isset($args['default_color']) ? (string) $args['default_color'] : '#111827';
            $this->render_color_field($key, $default_color, $help);
            return;
        }

        if ($type === 'number') {
            $this->render_number_field($key, $help);
            return;
        }

        if ($type === 'font_weight') {
            $this->render_font_weight_field($key, $help);
            return;
        }

        if ($type === 'typography_preset') {
            $this->render_typography_preset_field($key, $help);
            return;
        }

        $class_name = isset($args['class_name']) ? (string) $args['class_name'] : 'regular-text';
        $this->render_text_field($key, $class_name, $help);
    }

    private function get_labels(): array
    {
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        $language = strtolower(substr((string) $locale, 0, 2));

        if ($language === 'hu') {
            return [
                'page_title'                  => 'Store Locator Beallitasok',
                'menu_title'                  => 'Locator Beallitasok',
                'save_changes'                => 'Valtozasok mentese',
                'section_general_title'       => 'Altalanos terkep beallitasok',
                'section_general_description' => 'Globalis marker es alap terkep beallitasok.',
                'tab_map_settings'            => 'Terkep',
                'tab_search_input_settings'   => 'Kereso Input',
                'tab_search_button_settings'  => 'Kereso Gomb',
                'tab_info_panel_settings'     => 'Info Panel',
                'section_search_input_ui_title'     => 'Kereso input beallitasok',
                'section_search_input_ui_description'=> 'A frontend kereso mezo stilusai.',
                'section_search_button_ui_title'     => 'Kereso gomb beallitasok',
                'section_search_button_ui_description'=> 'A frontend kereso gomb stilusai.',
                'section_info_panel_ui_title' => 'Info panel tipografia',
                'section_info_panel_ui_description' => 'Tipografia beallitasok az info panel kulon reszeihez.',
                'info_group_title'            => 'Uzlet neve',
                'info_group_address'          => 'Cim',
                'info_group_phone'            => 'Telefon',
                'info_group_email'            => 'E-mail',
                'info_group_hours'            => 'Nyitvatartas',
                'field_font_family'           => 'Betutipus',
                'field_font_size'             => 'Betumeret',
                'field_font_weight'           => 'Betuvastagsag',
                'field_color'                 => 'Szin',
                'typography_source'           => 'Tipografia forras',
                'typography_source_help'      => 'Custom eseten kezi ertekek, Elementor globalis preset eseten automatikus atvetel.',
                'typography_custom'           => 'Custom (kezi)',
                'typography_primary'          => 'Elementor Global - Primary',
                'typography_secondary'        => 'Elementor Global - Secondary',
                'typography_text'             => 'Elementor Global - Text',
                'typography_accent'           => 'Elementor Global - Accent',
                'typography_selected_prefix'  => 'Kivalasztott tipografia:',
                'help_font_family'            => 'Pl.: inherit, Arial, "Open Sans", sans-serif',
                'help_font_size'              => 'Pixelben add meg (px).',
                'help_font_weight'            => '100-900 kozotti ertek.',
                'help_color'                  => 'Hex szin formatum, pelda: #111827',
                'marker_color'                => 'Globalis marker szin',
                'marker_color_help'           => 'Hex szin formatum, pelda: #2e7d32',
                'marker_image_url'            => 'Globalis marker kep URL',
                'marker_image_url_help'       => 'Ha uresen hagyod, a marker szin kerul hasznalatra.',
                'marker_active_image_url'     => 'Aktiv marker kep URL',
                'marker_active_image_url_help'=> 'A kivalsztott/aktiv marker kulon SVG vagy kep URL-je.',
                'section_tile_title'          => 'Tile layer (elokeszites)',
                'section_tile_description'    => 'Elokeszitett hely oriasi atalakitas nelkul cserelheto tile providerhez.',
                'tile_layer_url'              => 'Tile layer URL',
                'tile_layer_url_help'         => 'Opcionis. Kesoabbi frontend fazisban lesz hasznalva.',
                'tile_layer_attribution'      => 'Tile layer attribution',
                'marker_preview'              => 'Marker elo-nezet',
                'marker_preview_help'         => 'Itt latod, hogyan fog kinezni a globalis marker a beallitott szinnel vagy keppel.',
                'select_image'                => 'Kep kivalasztasa',
                'select_svg'                  => 'SVG feltoltese',
                'remove_image'                => 'Kep torlese',
                'select_active_image'         => 'Aktiv kep kivalasztasa',
                'select_active_svg'           => 'Aktiv SVG feltoltese',
                'remove_active_image'         => 'Aktiv kep torlese',
                'media_title'                 => 'Marker kep kivalasztasa',
                'media_button'                => 'Kep hasznalata',
                'svg_media_title'             => 'SVG fajl kivalasztasa',
                'svg_media_button'            => 'SVG hasznalata',
                'svg_only_error'              => 'Csak SVG fajl valaszthato.',
                'search_button_text'          => 'Kereso gomb szovege',
                'search_button_text_help'     => 'Ha uresen hagyod, a nyelvi alapertelmezett szoveg marad.',
                'search_button_bg_color'      => 'Kereso gomb hatterszine',
                'search_button_bg_color_help' => 'A keresesi gomb hatterszine.',
                'search_button_text_color'    => 'Kereso gomb szovegszine',
                'search_button_text_color_help'=> 'A keresesi gomb szovegenek szine.',
                'search_button_border_radius' => 'Kereso gomb kerekites',
                'search_button_border_radius_help'=> 'Pixelben add meg (px).',
                'search_button_border_width'  => 'Kereso gomb keret vastagsag',
                'search_button_border_width_help'=> 'Pixelben add meg (px).',
                'search_button_border_color'  => 'Kereso gomb keret szine',
                'search_button_border_color_help'=> 'A keresesi gomb keretenek szine.',
                'search_button_font_size'     => 'Kereso gomb betumeret',
                'search_button_font_size_help'=> 'Pixelben add meg (px).',
                'search_button_font_weight'   => 'Kereso gomb betuvastagsag',
                'search_button_font_weight_help'=> '100-900 kozotti ertek.',
                'search_button_letter_spacing' => 'Kereso gomb betu tavolsag',
                'search_button_letter_spacing_help' => 'Pixelben add meg (px).',
                'search_button_text_transform' => 'Kereso gomb szoveg atalakitasa',
                'search_button_text_transform_help' => 'none / uppercase / lowercase / capitalize',
                'search_input_text_color'     => 'Kereso input szovegszine',
                'search_input_text_color_help'=> 'A beirt szoveg szine (nem a placeholder).',
                'search_input_placeholder_text' => 'Kereso input placeholder szoveg',
                'search_input_placeholder_text_help' => 'Ha uresen hagyod, nyelvi alapertelmezett placeholder marad.',
                'search_input_placeholder_color' => 'Kereso input placeholder szine',
                'search_input_placeholder_color_help' => 'A placeholder szoveg szine.',
                'search_input_bg_color'       => 'Kereso input hatterszine',
                'search_input_bg_color_help'  => 'Az input mezo hatterszine.',
                'search_input_border_radius'  => 'Kereso input kerekites',
                'search_input_border_radius_help'=> 'Pixelben add meg (px).',
                'search_input_border_width'   => 'Kereso input keret vastagsag',
                'search_input_border_width_help'=> 'Pixelben add meg (px).',
                'search_input_border_color'   => 'Kereso input keret szine',
                'search_input_border_color_help'=> 'Az input keretenek szine.',
                'search_input_font_size'      => 'Kereso input betumeret',
                'search_input_font_size_help' => 'Pixelben add meg (px).',
                'search_input_font_weight'    => 'Kereso input betuvastagsag',
                'search_input_font_weight_help' => '100-900 kozotti ertek.',
            ];
        }

        if ($language === 'de') {
            return [
                'page_title'                  => 'Store Locator Einstellungen',
                'menu_title'                  => 'Locator Einstellungen',
                'save_changes'                => 'Aenderungen speichern',
                'section_general_title'       => 'Allgemeine Karteneinstellungen',
                'section_general_description' => 'Globale Marker- und Kartenstandardwerte.',
                'tab_map_settings'            => 'Karte',
                'tab_search_input_settings'   => 'Suchfeld Input',
                'tab_search_button_settings'  => 'Suchfeld Button',
                'tab_info_panel_settings'     => 'Info Panel',
                'section_search_input_ui_title'     => 'Suchfeld Input Einstellungen',
                'section_search_input_ui_description'=> 'Frontend-Stile fuer das Suchfeld.',
                'section_search_button_ui_title'     => 'Suchbutton Einstellungen',
                'section_search_button_ui_description'=> 'Frontend-Stile fuer den Suchbutton.',
                'section_info_panel_ui_title' => 'Info-Panel Typografie',
                'section_info_panel_ui_description' => 'Typografie-Einstellungen fuer die einzelnen Bereiche im Info-Panel.',
                'info_group_title'            => 'Standortname',
                'info_group_address'          => 'Adresse',
                'info_group_phone'            => 'Telefon',
                'info_group_email'            => 'E-Mail',
                'info_group_hours'            => 'Oeffnungszeiten',
                'field_font_family'           => 'Schriftfamilie',
                'field_font_size'             => 'Schriftgroesse',
                'field_font_weight'           => 'Schriftstaerke',
                'field_color'                 => 'Farbe',
                'typography_source'           => 'Typografie Quelle',
                'typography_source_help'      => 'Bei Custom werden eigene Werte genutzt, bei Elementor Global ein globales Preset.',
                'typography_custom'           => 'Custom (manuell)',
                'typography_primary'          => 'Elementor Global - Primary',
                'typography_secondary'        => 'Elementor Global - Secondary',
                'typography_text'             => 'Elementor Global - Text',
                'typography_accent'           => 'Elementor Global - Accent',
                'typography_selected_prefix'  => 'Ausgewaehlte Typografie:',
                'help_font_family'            => 'z.B.: inherit, Arial, "Open Sans", sans-serif',
                'help_font_size'              => 'Wert in Pixeln (px).',
                'help_font_weight'            => 'Wert zwischen 100 und 900.',
                'help_color'                  => 'Hex-Farbwert, Beispiel: #111827',
                'marker_color'                => 'Globale Markerfarbe',
                'marker_color_help'           => 'Hex-Farbwert, Beispiel: #2e7d32',
                'marker_image_url'            => 'Globale Markerbild-URL',
                'marker_image_url_help'       => 'Wenn leer, wird die Markerfarbe verwendet.',
                'marker_active_image_url'     => 'Aktive Markerbild-URL',
                'marker_active_image_url_help'=> 'Separates SVG/Bild fuer den aktiven Marker.',
                'section_tile_title'          => 'Tile Layer (Vorbereitung)',
                'section_tile_description'    => 'Platzhalter fuer spaeter austauschbare Tile-Provider.',
                'tile_layer_url'              => 'Tile Layer URL',
                'tile_layer_url_help'         => 'Optional. Wird in einer spaeteren Frontend-Phase verwendet.',
                'tile_layer_attribution'      => 'Tile Layer Attribution',
                'marker_preview'              => 'Marker Vorschau',
                'marker_preview_help'         => 'Hier sehen Sie, wie der globale Marker mit der aktuellen Farbe oder dem Bild aussehen wird.',
                'select_image'                => 'Bild aus Mediathek waehlen',
                'select_svg'                  => 'SVG hochladen',
                'remove_image'                => 'Bild entfernen',
                'select_active_image'         => 'Aktives Bild waehlen',
                'select_active_svg'           => 'Aktives SVG hochladen',
                'remove_active_image'         => 'Aktives Bild entfernen',
                'media_title'                 => 'Markerbild auswaehlen',
                'media_button'                => 'Bild verwenden',
                'svg_media_title'             => 'SVG-Datei auswaehlen',
                'svg_media_button'            => 'SVG verwenden',
                'svg_only_error'              => 'Es kann nur eine SVG-Datei ausgewaehlt werden.',
                'search_button_text'          => 'Suchbutton Text',
                'search_button_text_help'     => 'Leer lassen fuer den sprachabhaengigen Standardtext.',
                'search_button_bg_color'      => 'Suchbutton Hintergrundfarbe',
                'search_button_bg_color_help' => 'Hintergrundfarbe des Suchbuttons.',
                'search_button_text_color'    => 'Suchbutton Textfarbe',
                'search_button_text_color_help'=> 'Textfarbe des Suchbuttons.',
                'search_button_border_radius' => 'Suchbutton Rundung',
                'search_button_border_radius_help'=> 'Wert in Pixeln (px).',
                'search_button_border_width'  => 'Suchbutton Rahmenstaerke',
                'search_button_border_width_help'=> 'Wert in Pixeln (px).',
                'search_button_border_color'  => 'Suchbutton Rahmenfarbe',
                'search_button_border_color_help'=> 'Rahmenfarbe des Suchbuttons.',
                'search_button_font_size'     => 'Suchbutton Schriftgroesse',
                'search_button_font_size_help'=> 'Wert in Pixeln (px).',
                'search_button_font_weight'   => 'Suchbutton Schriftstaerke',
                'search_button_font_weight_help'=> 'Wert zwischen 100 und 900.',
                'search_button_letter_spacing' => 'Suchbutton Zeichenabstand',
                'search_button_letter_spacing_help' => 'Wert in Pixeln (px).',
                'search_button_text_transform' => 'Suchbutton Text-Umwandlung',
                'search_button_text_transform_help' => 'none / uppercase / lowercase / capitalize',
                'search_input_text_color'     => 'Suchfeld Textfarbe',
                'search_input_text_color_help'=> 'Farbe des eingegebenen Textes (nicht Placeholder).',
                'search_input_placeholder_text' => 'Suchfeld Placeholder Text',
                'search_input_placeholder_text_help' => 'Leer lassen fuer sprachabhaengigen Standard-Placeholder.',
                'search_input_placeholder_color' => 'Suchfeld Placeholder Farbe',
                'search_input_placeholder_color_help' => 'Farbe des Placeholder-Textes.',
                'search_input_bg_color'       => 'Suchfeld Hintergrundfarbe',
                'search_input_bg_color_help'  => 'Hintergrundfarbe des Suchfeldes.',
                'search_input_border_radius'  => 'Suchfeld Rundung',
                'search_input_border_radius_help'=> 'Wert in Pixeln (px).',
                'search_input_border_width'   => 'Suchfeld Rahmenstaerke',
                'search_input_border_width_help'=> 'Wert in Pixeln (px).',
                'search_input_border_color'   => 'Suchfeld Rahmenfarbe',
                'search_input_border_color_help'=> 'Rahmenfarbe des Suchfeldes.',
                'search_input_font_size'      => 'Suchfeld Schriftgroesse',
                'search_input_font_size_help' => 'Wert in Pixeln (px).',
                'search_input_font_weight'    => 'Suchfeld Schriftstaerke',
                'search_input_font_weight_help' => 'Wert zwischen 100 und 900.',
            ];
        }

        return [
            'page_title'                  => 'Store Locator Settings',
            'menu_title'                  => 'Locator Settings',
            'save_changes'                => 'Save Changes',
            'section_general_title'       => 'General Map Settings',
            'section_general_description' => 'Global marker and default map settings.',
            'tab_map_settings'            => 'Map',
            'tab_search_input_settings'   => 'Search Input',
            'tab_search_button_settings'  => 'Search Button',
            'tab_info_panel_settings'     => 'Info Panel',
            'section_search_input_ui_title'     => 'Search Input Styling',
            'section_search_input_ui_description'=> 'Frontend styles for the search input field.',
            'section_search_button_ui_title'     => 'Search Button Styling',
            'section_search_button_ui_description'=> 'Frontend styles for the search button.',
            'section_info_panel_ui_title' => 'Info Panel Typography',
            'section_info_panel_ui_description' => 'Typography settings for each info panel content block.',
            'info_group_title'            => 'Store Name',
            'info_group_address'          => 'Address',
            'info_group_phone'            => 'Phone',
            'info_group_email'            => 'Email',
            'info_group_hours'            => 'Opening Hours',
            'field_font_family'           => 'Font Family',
            'field_font_size'             => 'Font Size',
            'field_font_weight'           => 'Font Weight',
            'field_color'                 => 'Color',
            'typography_source'           => 'Typography Source',
            'typography_source_help'      => 'Custom uses manual values, Elementor Global uses the selected global preset.',
            'typography_custom'           => 'Custom (manual)',
            'typography_primary'          => 'Elementor Global - Primary',
            'typography_secondary'        => 'Elementor Global - Secondary',
            'typography_text'             => 'Elementor Global - Text',
            'typography_accent'           => 'Elementor Global - Accent',
            'typography_selected_prefix'  => 'Selected typography:',
            'help_font_family'            => 'Example: inherit, Arial, "Open Sans", sans-serif',
            'help_font_size'              => 'Enter value in pixels (px).',
            'help_font_weight'            => 'Value between 100 and 900.',
            'help_color'                  => 'Hex color format, for example: #111827',
            'marker_color'                => 'Global Marker Color',
            'marker_color_help'           => 'Hex color format, for example: #2e7d32',
            'marker_image_url'            => 'Global Marker Image URL',
            'marker_image_url_help'       => 'Leave empty to use marker color.',
            'marker_active_image_url'     => 'Active Marker Image URL',
            'marker_active_image_url_help'=> 'Separate SVG/image URL for selected active marker.',
            'section_tile_title'          => 'Tile Layer (Placeholder)',
            'section_tile_description'    => 'Prepared placeholder for a swappable tile provider later.',
            'tile_layer_url'              => 'Tile Layer URL',
            'tile_layer_url_help'         => 'Optional. Will be used in a later frontend phase.',
            'tile_layer_attribution'      => 'Tile Layer Attribution',
            'marker_preview'              => 'Marker Preview',
            'marker_preview_help'         => 'This shows how the global marker will look with the selected color or image.',
            'select_image'                => 'Select from Media Library',
            'select_svg'                  => 'Upload SVG',
            'remove_image'                => 'Remove Image',
            'select_active_image'         => 'Select Active Image',
            'select_active_svg'           => 'Upload Active SVG',
            'remove_active_image'         => 'Remove Active Image',
            'media_title'                 => 'Select Marker Image',
            'media_button'                => 'Use this image',
            'svg_media_title'             => 'Select SVG File',
            'svg_media_button'            => 'Use this SVG',
            'svg_only_error'              => 'Only SVG files are allowed for this action.',
            'search_button_text'          => 'Search Button Text',
            'search_button_text_help'     => 'Leave empty to keep the language-based default text.',
            'search_button_bg_color'      => 'Search Button Background Color',
            'search_button_bg_color_help' => 'Background color of the search button.',
            'search_button_text_color'    => 'Search Button Text Color',
            'search_button_text_color_help'=> 'Text color of the search button.',
            'search_button_border_radius' => 'Search Button Border Radius',
            'search_button_border_radius_help'=> 'Enter value in pixels (px).',
            'search_button_border_width'  => 'Search Button Border Width',
            'search_button_border_width_help'=> 'Enter value in pixels (px).',
            'search_button_border_color'  => 'Search Button Border Color',
            'search_button_border_color_help'=> 'Border color of the search button.',
            'search_button_font_size'     => 'Search Button Font Size',
            'search_button_font_size_help'=> 'Enter value in pixels (px).',
            'search_button_font_weight'   => 'Search Button Font Weight',
            'search_button_font_weight_help'=> 'Value between 100 and 900.',
            'search_button_letter_spacing' => 'Search Button Letter Spacing',
            'search_button_letter_spacing_help' => 'Enter value in pixels (px).',
            'search_button_text_transform' => 'Search Button Text Transform',
            'search_button_text_transform_help' => 'none / uppercase / lowercase / capitalize',
            'search_input_text_color'     => 'Search Input Text Color',
            'search_input_text_color_help'=> 'Color of entered text (not placeholder).',
            'search_input_placeholder_text' => 'Search Input Placeholder Text',
            'search_input_placeholder_text_help' => 'Leave empty to keep the language-based default placeholder.',
            'search_input_placeholder_color' => 'Search Input Placeholder Color',
            'search_input_placeholder_color_help' => 'Color of the placeholder text.',
            'search_input_bg_color'       => 'Search Input Background Color',
            'search_input_bg_color_help'  => 'Background color of the search input field.',
            'search_input_border_radius'  => 'Search Input Border Radius',
            'search_input_border_radius_help'=> 'Enter value in pixels (px).',
            'search_input_border_width'   => 'Search Input Border Width',
            'search_input_border_width_help'=> 'Enter value in pixels (px).',
            'search_input_border_color'   => 'Search Input Border Color',
            'search_input_border_color_help'=> 'Border color of the search input field.',
            'search_input_font_size'      => 'Search Input Font Size',
            'search_input_font_size_help' => 'Enter value in pixels (px).',
            'search_input_font_weight'    => 'Search Input Font Weight',
            'search_input_font_weight_help' => 'Value between 100 and 900.',
        ];
    }
}
