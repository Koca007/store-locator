<?php

declare(strict_types=1);

namespace StoreLocator\PostType;

final class StorePostType
{
    public const POST_TYPE = 'sl_store';

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
    }

    public function register_post_type(): void
    {
        $labels = [
            'name'               => __('Stores', 'store-locator'),
            'singular_name'      => __('Store', 'store-locator'),
            'menu_name'          => __('Stores', 'store-locator'),
            'name_admin_bar'     => __('Store', 'store-locator'),
            'add_new'            => __('Add New', 'store-locator'),
            'add_new_item'       => __('Add New Store', 'store-locator'),
            'new_item'           => __('New Store', 'store-locator'),
            'edit_item'          => __('Edit Store', 'store-locator'),
            'view_item'          => __('View Store', 'store-locator'),
            'all_items'          => __('All Stores', 'store-locator'),
            'search_items'       => __('Search Stores', 'store-locator'),
            'parent_item_colon'  => __('Parent Stores:', 'store-locator'),
            'not_found'          => __('No stores found.', 'store-locator'),
            'not_found_in_trash' => __('No stores found in Trash.', 'store-locator'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_admin_bar'  => true,
            'show_in_nav_menus'  => false,
            'show_in_rest'       => false,
            'menu_position'      => 26,
            'menu_icon'          => 'dashicons-location-alt',
            'supports'           => ['title'],
            'has_archive'        => false,
            'publicly_queryable' => false,
            'rewrite'            => false,
            'query_var'          => false,
        ];

        register_post_type(self::POST_TYPE, $args);
    }
}

