<?php

declare(strict_types=1);

namespace StoreLocator\Admin;

final class SvgUploadSupport
{
    public function register(): void
    {
        add_filter('upload_mimes', [$this, 'allow_svg_mime']);
        add_filter('wp_check_filetype_and_ext', [$this, 'fix_svg_filetype'], 10, 5);
        add_filter('wp_handle_upload_prefilter', [$this, 'validate_svg_upload']);
    }

    public function allow_svg_mime(array $mimes): array
    {
        if (! $this->can_upload_svg()) {
            return $mimes;
        }

        $mimes['svg'] = 'image/svg+xml';

        return $mimes;
    }

    public function fix_svg_filetype(array $data, string $file, string $filename, ?array $mimes = null, $real_mime = null): array
    {
        if (! $this->can_upload_svg()) {
            return $data;
        }

        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension !== 'svg') {
            return $data;
        }

        $data['ext'] = 'svg';
        $data['type'] = 'image/svg+xml';

        return $data;
    }

    public function validate_svg_upload(array $file): array
    {
        if (! $this->can_upload_svg()) {
            return $file;
        }

        $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

        if ($extension !== 'svg') {
            return $file;
        }

        $tmp_name = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';

        if ($tmp_name === '' || ! is_readable($tmp_name)) {
            $file['error'] = __('SVG upload failed: temporary file is not readable.', 'store-locator');
            return $file;
        }

        $content = file_get_contents($tmp_name);

        if (! is_string($content) || $content === '') {
            $file['error'] = __('SVG upload failed: file is empty or unreadable.', 'store-locator');
            return $file;
        }

        if (! $this->is_safe_svg($content)) {
            $file['error'] = __('SVG upload blocked for security reasons. Remove scripts and event handlers.', 'store-locator');
            return $file;
        }

        return $file;
    }

    private function can_upload_svg(): bool
    {
        return is_admin() && current_user_can('manage_options');
    }

    private function is_safe_svg(string $content): bool
    {
        $content = ltrim($content, "\xEF\xBB\xBF");
        $lower = strtolower($content);

        if (strpos($lower, '<svg') === false) {
            return false;
        }

        $blocked_patterns = [
            '/<script\b/i',
            '/<foreignobject\b/i',
            '/\bon\w+\s*=/i',
            '/\b(?:href|xlink:href)\s*=\s*["\']?\s*javascript:/i',
            '/<\?php/i',
        ];

        foreach ($blocked_patterns as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                return false;
            }
        }

        return true;
    }
}
