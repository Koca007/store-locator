<?php

declare(strict_types=1);

namespace StoreLocator\Meta;

use StoreLocator\PostType\StorePostType;

final class StoreMetaBoxes
{
    private const NONCE_ACTION = 'sl_store_meta_save';
    private const NONCE_NAME = 'sl_store_meta_nonce';

    /// Canonical field list for future frontend and import/export phases.
    private const FIELDS = [
        'address'       => '_sl_address',
        'zip'           => '_sl_zip',
        'city'          => '_sl_city',
        'phone'         => '_sl_phone',
        'email'         => '_sl_email',
        'website'       => '_sl_website',
        'product_ranges'=> '_sl_product_ranges',
        'opening_hours' => '_sl_opening_hours',
    ];

    private const WEEK_DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ];

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_' . StorePostType::POST_TYPE, [$this, 'save_meta'], 10, 2);
    }

    public function register_meta_boxes(): void
    {
        add_meta_box(
            'sl_store_details',
            __('Store Details', 'store-locator'),
            [$this, 'render_store_details_meta_box'],
            StorePostType::POST_TYPE,
            'normal',
            'default'
        );
    }

    public function render_store_details_meta_box(\WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $labels = $this->get_ui_labels();

        $values = [];

        foreach (self::FIELDS as $field_name => $meta_key) {
            $values[$field_name] = (string) get_post_meta($post->ID, $meta_key, true);
        }

        $opening_hours = $this->decode_opening_hours($values['opening_hours']);

        ?>
        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row"><label for="sl_address"><?php echo esc_html($labels['address']); ?></label></th>
                <td><input type="text" class="regular-text" id="sl_address" name="sl_store_meta[address]" value="<?php echo esc_attr($values['address']); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="sl_zip"><?php echo esc_html($labels['zip_code']); ?></label></th>
                <td><input type="text" class="regular-text" id="sl_zip" name="sl_store_meta[zip]" value="<?php echo esc_attr($values['zip']); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="sl_city"><?php echo esc_html($labels['city']); ?></label></th>
                <td><input type="text" class="regular-text" id="sl_city" name="sl_store_meta[city]" value="<?php echo esc_attr($values['city']); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="sl_phone"><?php echo esc_html($labels['phone']); ?></label></th>
                <td><input type="text" class="regular-text" id="sl_phone" name="sl_store_meta[phone]" value="<?php echo esc_attr($values['phone']); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="sl_email"><?php echo esc_html($labels['email']); ?></label></th>
                <td><input type="email" class="regular-text" id="sl_email" name="sl_store_meta[email]" value="<?php echo esc_attr($values['email']); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="sl_website"><?php echo esc_html($labels['website']); ?></label></th>
                <td><input type="url" class="regular-text" id="sl_website" name="sl_store_meta[website]" value="<?php echo esc_attr($values['website']); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="sl_product_ranges"><?php echo esc_html($labels['product_ranges']); ?></label></th>
                <td><input type="text" class="regular-text" id="sl_product_ranges" name="sl_store_meta[product_ranges]" value="<?php echo esc_attr($values['product_ranges']); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html($labels['opening_hours']); ?></th>
                <td>
                    <table class="widefat striped" style="max-width: 620px;">
                        <thead>
                        <tr>
                            <th><?php echo esc_html($labels['day']); ?></th>
                            <th><?php echo esc_html($labels['from']); ?></th>
                            <th><?php echo esc_html($labels['to']); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->get_week_day_labels() as $day_key => $day_label) : ?>
                            <tr>
                                <td><?php echo esc_html($day_label); ?></td>
                                <td>
                                    <input
                                        type="time"
                                        name="sl_store_meta[opening_hours][<?php echo esc_attr($day_key); ?>][from]"
                                        value="<?php echo esc_attr($opening_hours[$day_key]['from']); ?>"
                                    />
                                </td>
                                <td>
                                    <input
                                        type="time"
                                        name="sl_store_meta[opening_hours][<?php echo esc_attr($day_key); ?>][to]"
                                        value="<?php echo esc_attr($opening_hours[$day_key]['to']); ?>"
                                    />
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="description">
                        <?php echo esc_html($labels['opening_hours_help']); ?>
                    </p>
                </td>
            </tr>
            </tbody>
        </table>
        <?php
    }

    public function save_meta(int $post_id, \WP_Post $post): void
    {
        if ($post->post_type !== StorePostType::POST_TYPE) {
            return;
        }

        if (! isset($_POST[self::NONCE_NAME])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME]));

        if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
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

        if (! isset($_POST['sl_store_meta']) || ! is_array($_POST['sl_store_meta'])) {
            return;
        }

        $raw_meta = wp_unslash($_POST['sl_store_meta']);

        foreach (self::FIELDS as $field_name => $meta_key) {
            if ($field_name === 'opening_hours') {
                $raw_opening_hours = isset($raw_meta[$field_name]) && is_array($raw_meta[$field_name]) ? $raw_meta[$field_name] : [];
                $opening_hours = $this->sanitize_opening_hours($raw_opening_hours);

                if ($opening_hours === '') {
                    delete_post_meta($post_id, $meta_key);
                    continue;
                }

                update_post_meta($post_id, $meta_key, $opening_hours);
                continue;
            }

            $raw_value = isset($raw_meta[$field_name]) ? (string) $raw_meta[$field_name] : '';
            $value = $this->sanitize_field_value($field_name, $raw_value);

            if ($value === '') {
                delete_post_meta($post_id, $meta_key);
                continue;
            }

            update_post_meta($post_id, $meta_key, $value);
        }
    }

    private function sanitize_field_value(string $field_name, string $raw_value): string
    {
        switch ($field_name) {
            case 'email':
                $email = sanitize_email($raw_value);
                return is_email($email) ? $email : '';

            case 'website':
                $url = esc_url_raw($raw_value);
                return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';

            case 'opening_hours':
                return sanitize_textarea_field($raw_value);

            default:
                return sanitize_text_field($raw_value);
        }
    }

    private function sanitize_opening_hours(array $raw_opening_hours): string
    {
        $sanitized = [];

        foreach (self::WEEK_DAYS as $day_key) {
            $from = '';
            $to = '';

            if (isset($raw_opening_hours[$day_key]) && is_array($raw_opening_hours[$day_key])) {
                $from = isset($raw_opening_hours[$day_key]['from']) ? $this->sanitize_time((string) $raw_opening_hours[$day_key]['from']) : '';
                $to = isset($raw_opening_hours[$day_key]['to']) ? $this->sanitize_time((string) $raw_opening_hours[$day_key]['to']) : '';
            }

            if ($from === '' && $to === '') {
                continue;
            }

            $sanitized[$day_key] = [
                'from' => $from,
                'to'   => $to,
            ];
        }

        if (empty($sanitized)) {
            return '';
        }

        $encoded = wp_json_encode($sanitized);

        return is_string($encoded) ? $encoded : '';
    }

    private function sanitize_time(string $value): string
    {
        $clean = trim($value);

        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $clean)) {
            return '';
        }

        return $clean;
    }

    private function decode_opening_hours(string $raw_value): array
    {
        $defaults = [];

        foreach (self::WEEK_DAYS as $day_key) {
            $defaults[$day_key] = [
                'from' => '',
                'to'   => '',
            ];
        }

        if ($raw_value === '') {
            return $defaults;
        }

        $decoded = json_decode($raw_value, true);

        if (! is_array($decoded)) {
            return $defaults;
        }

        foreach (self::WEEK_DAYS as $day_key) {
            if (! isset($decoded[$day_key]) || ! is_array($decoded[$day_key])) {
                continue;
            }

            $defaults[$day_key]['from'] = isset($decoded[$day_key]['from']) ? $this->sanitize_time((string) $decoded[$day_key]['from']) : '';
            $defaults[$day_key]['to'] = isset($decoded[$day_key]['to']) ? $this->sanitize_time((string) $decoded[$day_key]['to']) : '';
        }

        return $defaults;
    }

    private function get_week_day_labels(): array
    {
        $labels = [];

        global $wp_locale;

        foreach (self::WEEK_DAYS as $day_key) {
            $localized = '';

            if ($wp_locale instanceof \WP_Locale) {
                $weekday_index = (int) gmdate('w', strtotime($day_key));
                $localized = (string) $wp_locale->get_weekday($weekday_index);
            }

            if ($localized === '') {
                $localized = ucfirst($day_key);
            }

            $labels[$day_key] = $localized;
        }

        return $labels;
    }

    private function get_ui_labels(): array
    {
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        $language = strtolower(substr((string) $locale, 0, 2));

        if ($language === 'hu') {
            return [
                'address'            => 'Cim',
                'zip_code'           => 'Iranyitoszam',
                'city'               => 'Varos',
                'phone'              => 'Telefonszam',
                'email'              => 'E-mail',
                'website'            => 'Weboldal',
                'product_ranges'     => 'Termekkorok',
                'opening_hours'      => 'Nyitvatartas',
                'day'                => 'Nap',
                'from'               => 'Mettol',
                'to'                 => 'Meddig',
                'opening_hours_help' => 'Add meg a nyitvatartast napokra bontva. Ha az uzlet zarva van, hagyd uresen mindket mezot.',
            ];
        }

        if ($language === 'de') {
            return [
                'address'            => 'Adresse',
                'zip_code'           => 'Postleitzahl',
                'city'               => 'Stadt',
                'phone'              => 'Telefon',
                'email'              => 'E-Mail',
                'website'            => 'Webseite',
                'product_ranges'     => 'Produktbereiche',
                'opening_hours'      => 'Oeffnungszeiten',
                'day'                => 'Tag',
                'from'               => 'Von',
                'to'                 => 'Bis',
                'opening_hours_help' => 'Geben Sie die Oeffnungszeiten pro Tag ein. Lassen Sie beide Felder leer, wenn das Geschaeft an diesem Tag geschlossen ist.',
            ];
        }

        return [
            'address'            => 'Address',
            'zip_code'           => 'ZIP Code',
            'city'               => 'City',
            'phone'              => 'Phone',
            'email'              => 'Email',
            'website'            => 'Website',
            'product_ranges'     => 'Product Ranges',
            'opening_hours'      => 'Opening Hours',
            'day'                => 'Day',
            'from'               => 'From',
            'to'                 => 'To',
            'opening_hours_help' => 'Enter opening hours per day. Leave both fields empty if the store is closed that day.',
        ];
    }
}
