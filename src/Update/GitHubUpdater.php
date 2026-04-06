<?php

declare(strict_types=1);

namespace StoreLocator\Update;

final class GitHubUpdater
{
    private const CACHE_TTL = 300;

    private string $plugin_file;
    private string $plugin_basename;
    private string $plugin_slug;
    private string $repository;
    private string $token;

    public function __construct(string $plugin_file, string $repository, string $token = '')
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->plugin_slug = dirname($this->plugin_basename);
        $this->repository = trim($repository);
        $this->token = trim($token);
    }

    public function register(): void
    {
        if ($this->repository === '') {
            return;
        }

        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api', [$this, 'inject_plugin_information'], 10, 3);
    }

    public function inject_update($transient)
    {
        if (! is_object($transient) || ! isset($transient->checked) || ! is_array($transient->checked)) {
            return $transient;
        }

        if (! isset($transient->checked[$this->plugin_basename])) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if ($release === null) {
            return $transient;
        }

        $current_version = (string) $transient->checked[$this->plugin_basename];
        if (version_compare($release['version'], $current_version, '<=')) {
            return $transient;
        }

        $update = (object) [
            'slug' => $this->plugin_slug,
            'plugin' => $this->plugin_basename,
            'new_version' => $release['version'],
            'url' => $release['html_url'],
            'package' => $release['package_url'],
        ];

        if (! isset($transient->response) || ! is_array($transient->response)) {
            $transient->response = [];
        }

        $transient->response[$this->plugin_basename] = $update;

        return $transient;
    }

    public function inject_plugin_information($result, string $action, $args)
    {
        if ($action !== 'plugin_information' || ! is_object($args) || ! isset($args->slug)) {
            return $result;
        }

        if ((string) $args->slug !== $this->plugin_slug) {
            return $result;
        }

        $release = $this->get_latest_release();
        if ($release === null) {
            return $result;
        }

        return (object) [
            'name' => 'Store Locator',
            'slug' => $this->plugin_slug,
            'version' => $release['version'],
            'author' => '<a href="' . esc_url($release['html_url']) . '">Store Locator</a>',
            'homepage' => $release['html_url'],
            'download_link' => $release['package_url'],
            'sections' => [
                'description' => esc_html__('Store Locator plugin updates delivered from GitHub Releases.', 'store-locator'),
                'changelog' => wp_kses_post(nl2br(esc_html($release['body']))),
            ],
        ];
    }

    private function get_latest_release(): ?array
    {
        $cache_key = $this->get_cache_key();
        $cache = get_transient($cache_key);
        if (is_array($cache)) {
            return $cache;
        }

        $endpoint = 'https://api.github.com/repos/' . $this->repository . '/releases/latest';
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'store-locator/' . STORE_LOCATOR_VERSION,
        ];

        if ($this->token !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }

        $response = wp_remote_get(
            $endpoint,
            [
                'headers' => $headers,
                'timeout' => 12,
            ]
        );

        if (is_wp_error($response)) {
            return null;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        if (! is_string($body) || $body === '') {
            return null;
        }

        $data = json_decode($body, true);
        if (! is_array($data)) {
            return null;
        }

        $version = $this->normalize_version((string) ($data['tag_name'] ?? ''));
        if ($version === '') {
            return null;
        }

        $package_url = $this->find_release_zip_url($data);
        if ($package_url === '') {
            return null;
        }

        $release = [
            'version' => $version,
            'package_url' => $package_url,
            'html_url' => (string) ($data['html_url'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
        ];

        set_transient($cache_key, $release, self::CACHE_TTL);

        return $release;
    }

    private function normalize_version(string $tag): string
    {
        $tag = trim($tag);
        if ($tag === '') {
            return '';
        }

        if (strpos($tag, 'v') === 0 || strpos($tag, 'V') === 0) {
            return substr($tag, 1);
        }

        return $tag;
    }

    private function find_release_zip_url(array $release): string
    {
        $assets = $release['assets'] ?? [];
        if (is_array($assets)) {
            foreach ($assets as $asset) {
                if (! is_array($asset)) {
                    continue;
                }

                $name = isset($asset['name']) ? strtolower((string) $asset['name']) : '';
                $url = isset($asset['browser_download_url']) ? (string) $asset['browser_download_url'] : '';
                if ($url === '') {
                    continue;
                }

                if (substr($name, -4) === '.zip') {
                    return $url;
                }
            }
        }

        return '';
    }

    private function get_cache_key(): string
    {
        return 'sl_github_release_' . md5($this->repository);
    }
}
