<?php

declare(strict_types=1);

namespace StoreLocator\Geocoding;

final class GeocodingResult
{
    private float $latitude;

    private float $longitude;

    public function __construct(float $latitude, float $longitude)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function get_latitude(): float
    {
        return $this->latitude;
    }

    public function get_longitude(): float
    {
        return $this->longitude;
    }
}

