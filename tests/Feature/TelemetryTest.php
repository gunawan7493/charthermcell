<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelemetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function fakeBlynk(array $pins = [], bool $online = true): void
    {
        $pins = array_merge([
            'V0' => 64.5,
            'V1' => 31.2,
            'V2' => 33.3,
            'V3' => 0.512,
            'V4' => 12.34,
            'V5' => 6.32,
            'V6' => 2,
        ], $pins);

        Http::fake([
            'blynk.cloud/external/api/get*' => Http::response($pins),
            'blynk.cloud/external/api/isHardwareConnected*' => Http::response($online ? 'true' : 'false'),
        ]);
    }

    public function test_it_returns_a_normalised_snapshot(): void
    {
        config(['blynk.token' => 'test-token']);
        $this->fakeBlynk();

        $response = $this->getJson('/api/telemetry');

        $response->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('online', true)
            ->assertJsonPath('readings.temperature_hot', 64.5)
            ->assertJsonPath('readings.temperature_cold', 31.2)
            ->assertJsonPath('readings.delta_temperature', 33.3)
            ->assertJsonPath('readings.voltage', 0.512)
            ->assertJsonPath('readings.current', 12.34)
            ->assertJsonPath('readings.power', 6.32)
            ->assertJsonPath('status.code', 2)
            ->assertJsonPath('status.key', 'optimal')
            ->assertJsonPath('error', null);

        Http::assertSent(function (Request $request): bool {
            if (str_contains($request->url(), 'isHardwareConnected')) {
                return str_contains($request->url(), 'token=test-token');
            }

            return str_contains($request->url(), 'get?V0&V1&V2&V3&V4&V5&V6')
                && str_contains($request->url(), 'token=test-token');
        });
    }

    public function test_it_reports_an_offline_device(): void
    {
        config(['blynk.token' => 'test-token']);
        $this->fakeBlynk(online: false);

        $this->getJson('/api/telemetry')
            ->assertOk()
            ->assertJsonPath('online', false)
            ->assertJsonPath('status.code', 2);
    }

    public function test_it_derives_status_when_device_sends_no_status(): void
    {
        config(['blynk.token' => 'test-token']);
        $this->fakeBlynk(['V6' => 0, 'V0' => 42.0]);

        $this->getJson('/api/telemetry')
            ->assertOk()
            ->assertJsonPath('status.code', 0)
            ->assertJsonPath('status.key', 'cold');
    }

    public function test_it_reports_a_missing_token_without_calling_blynk(): void
    {
        config(['blynk.token' => null]);

        $this->getJson('/api/telemetry')
            ->assertOk()
            ->assertJsonPath('configured', false)
            ->assertJsonPath('online', false)
            ->assertJsonPath('error', 'Token Blynk belum diatur di server.');

        Http::assertNothingSent();
    }

    public function test_it_survives_a_blynk_error(): void
    {
        config(['blynk.token' => 'test-token']);

        Http::fake([
            'blynk.cloud/external/api/get*' => Http::response([
                'error' => ['message' => 'Requested pin doesn\'t exist in the product CharThermCell'],
            ], 400),
            'blynk.cloud/external/api/isHardwareConnected*' => Http::response('true'),
        ]);

        $this->getJson('/api/telemetry')
            ->assertOk()
            ->assertJsonPath('online', false)
            ->assertJsonPath('error', "Requested pin doesn't exist in the product CharThermCell");
    }

    public function test_snapshots_are_cached_briefly(): void
    {
        config(['blynk.token' => 'test-token']);
        $this->fakeBlynk();

        $this->getJson('/api/telemetry')->assertOk();
        $this->getJson('/api/telemetry')->assertOk();

        Http::assertSentCount(2);
    }

    public function test_telemetry_is_rate_limited(): void
    {
        config(['blynk.token' => 'test-token']);
        $this->fakeBlynk();

        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/telemetry');
        }

        $this->getJson('/api/telemetry')->assertStatus(429);
    }
}
