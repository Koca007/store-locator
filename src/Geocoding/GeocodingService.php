<?php

declare(strict_types=1);

namespace StoreLocator\Geocoding;

final class GeocodingService
{
    private const HASH_VERSION = 'v2-hu-normalized';

    private GeocodingProviderInterface $provider;

    public function __construct(GeocodingProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    public function geocode_address(string $address, string $zip, string $city): ?GeocodingResult
    {
        $address = $this->normalize_part($address);
        $zip = $this->normalize_part($zip);
        $city = $this->normalize_part($city);

        $parts = array_filter([$address, $zip, $city]);

        if (empty($parts)) {
            return null;
        }

        /// Force country context to improve consistency for local HU addresses.
        $parts[] = 'Hungary';

        return $this->provider->geocode(implode(', ', $parts));
    }

    public function build_source_hash(string $address, string $zip, string $city): string
    {
        $normalized = implode(
            '|',
            [
                self::HASH_VERSION,
                $this->normalize_part($address),
                $this->normalize_part($zip),
                $this->normalize_part($city),
                'HU',
            ]
        );

        return md5($normalized);
    }

    private function normalize_part(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = str_replace([',,', ' ,', ', '], ',', $value);
        $value = trim((string) $value, ", \t\n\r\0\x0B");

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }
}
