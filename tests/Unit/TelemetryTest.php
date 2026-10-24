<?php

namespace Tests\Unit;

use App\Support\Telemetry;
use Tests\TestCase;

class TelemetryTest extends TestCase
{
    public function test_it_uses_the_device_status_when_present(): void
    {
        $telemetry = new Telemetry(
            configured: true,
            online: true,
            temperatureHot: 20.0,
            statusCode: 2,
        );

        $this->assertSame(2, $telemetry->resolvedStatusCode());
    }

    public function test_it_derives_the_status_from_temperature(): void
    {
        $this->assertSame(0, (new Telemetry(true, true, temperatureHot: 30.0))->resolvedStatusCode());
        $this->assertSame(1, (new Telemetry(true, true, temperatureHot: 55.0))->resolvedStatusCode());
        $this->assertSame(2, (new Telemetry(true, true, temperatureHot: 75.0))->resolvedStatusCode());
    }

    public function test_an_unknown_device_status_falls_back_to_temperature(): void
    {
        $telemetry = new Telemetry(true, true, temperatureHot: 55.0, statusCode: 9);

        $this->assertSame(1, $telemetry->resolvedStatusCode());
    }

    public function test_missing_readings_are_exposed_as_null(): void
    {
        $payload = Telemetry::unavailable('Perangkat tidak dapat dihubungi.')->toArray();

        $this->assertNull($payload['readings']['temperature_hot']);
        $this->assertFalse($payload['online']);
        $this->assertSame('Perangkat tidak dapat dihubungi.', $payload['error']);
    }

    public function test_it_can_be_rehydrated_from_a_cached_array(): void
    {
        $original = new Telemetry(
            configured: true,
            online: true,
            temperatureHot: 64.5,
            temperatureCold: 31.2,
            deltaTemperature: 33.3,
            voltage: 0.512,
            current: 12.34,
            power: 6.32,
            statusCode: 2,
        );

        $copy = Telemetry::fromArray($original->toArray());

        $this->assertSame($original->toArray()['readings'], $copy->toArray()['readings']);
        $this->assertSame(2, $copy->resolvedStatusCode());
        $this->assertTrue($copy->online);
    }
}
