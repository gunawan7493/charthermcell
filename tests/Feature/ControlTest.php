<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['blynk.token' => 'test-token']);
    }

    private function fakeBlynk(): void
    {
        Http::fake([
            'blynk.cloud/external/api/update*' => Http::response('OK'),
        ]);
    }

    public function test_it_can_enable_the_buzzer(): void
    {
        $this->fakeBlynk();

        $this->postJson('/api/controls/buzzer', ['enabled' => true])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('enabled', true);

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/update?')
                && str_contains($request->url(), 'V7=1')
                && str_contains($request->url(), 'token=test-token');
        });
    }

    public function test_it_can_disable_the_buzzer(): void
    {
        $this->fakeBlynk();

        $this->postJson('/api/controls/buzzer', ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('enabled', false);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'V7=0'));
    }

    public function test_it_can_request_calibration(): void
    {
        $this->fakeBlynk();

        $this->postJson('/api/controls/calibrate')
            ->assertOk()
            ->assertJsonPath('ok', true);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'V8=1'));
    }

    public function test_it_validates_the_enabled_flag(): void
    {
        $this->postJson('/api/controls/buzzer', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('enabled');

        Http::assertNothingSent();
    }

    public function test_it_reports_a_blynk_rejection(): void
    {
        Http::fake([
            'blynk.cloud/external/api/update*' => Http::response([
                'error' => ['message' => "Requested pin doesn't exist in the product CharThermCell"],
            ], 400),
        ]);

        $this->postJson('/api/controls/buzzer', ['enabled' => true])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'Datastream kontrol V7 belum tersedia di produk Blynk.');
    }

    public function test_controls_can_be_disabled(): void
    {
        config(['blynk.controls_enabled' => false]);

        $this->postJson('/api/controls/buzzer', ['enabled' => true])
            ->assertStatus(409)
            ->assertJsonPath('ok', false);

        Http::assertNothingSent();
    }

    public function test_control_requests_are_rate_limited(): void
    {
        $this->fakeBlynk();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/controls/buzzer', ['enabled' => true])->assertOk();
        }

        $this->postJson('/api/controls/buzzer', ['enabled' => true])->assertStatus(429);
    }
}
