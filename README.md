# Store Locator for WordPress

Store Locator is a WordPress plugin that displays stores on a Leaflet map with frontend search/filter and an admin management interface.

## Version

Current stable release: `1.0.0`

## Main Features

- Custom post type for stores
- Admin store details (address, zip, city, phone, email, website, opening hours)
- Address-based coordinate generation workflow
- Leaflet map rendering on frontend
- Marker clustering
- Global marker style configuration (color/image/SVG)
- Search UI and map interaction
- Info panel with store details and opening hours
- Multilingual-ready labels via WordPress i18n functions
- Automatic plugin updates from GitHub Releases

## Requirements

- WordPress 6.x+
- PHP 7.4+
- Composer (for autoload generation in development)

## Installation

1. Upload the `store-locator` plugin folder to `wp-content/plugins/`.
2. Make sure `vendor/autoload.php` exists (run `composer install` in plugin root if needed).
3. Activate **Store Locator** in WordPress admin.

## Shortcodes

- `[store_locator_map]` - renders the map area
- `[store_locator_search]` - renders the search area

You can place them together or separately, depending on page layout needs.

## Automatic Updates (GitHub)

This repository uses a GitHub Actions workflow that automatically creates a release when the plugin version is bumped.

Release flow:

1. Increase plugin version in `store-locator.php` (`Version` header + `STORE_LOCATOR_VERSION` constant).
2. Commit and push to `main`.
3. GitHub Action creates:
   - tag `vX.Y.Z`
   - release zip `store-locator-X.Y.Z.zip`
4. WordPress sites with the plugin installed can update via normal **Update** button.

## Import / Export

- Admin path: `Stores -> Import / Export`
- Format: CSV (Excel compatible, UTF-8 BOM, semicolon/comma import support)
- Header row is supported (optional but recommended)
- Sample file download is available on the Import / Export screen

Supported columns:

`name,address,zip,city,phone,email,website,opening_hours,latitude,longitude,status`

`opening_hours` format example:

`monday=07:00-16:00|tuesday=07:00-16:00|sunday=-`

## Repository

- GitHub: <https://github.com/Koca007/store-locator>

## License

GPL-2.0-or-later
