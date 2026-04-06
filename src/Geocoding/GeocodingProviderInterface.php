<?php

declare(strict_types=1);

namespace StoreLocator\Geocoding;

interface GeocodingProviderInterface
{
    public function geocode(string $query): ?GeocodingResult;
}

