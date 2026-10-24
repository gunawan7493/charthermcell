<?php

namespace App\Services\Blynk;

use App\Support\Telemetry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin, server-side client for the Blynk Cloud external API.
 *
 * The auth token never leaves the server: the browser talks to this
 * application, this application talks to Blynk.
 */
class BlynkClient
{
    private const CACHE_KEY = 'blynk:telemetry';

    public function configured(): bool
    {
        return filled(config('blynk.token'));
    }

    public function controlsEnabled(): bool
    {
        return $this->configured() && (bool) config('blynk.controls_enabled', true);
    }

    /**
     * Latest telemetry, coalesced through a very short cache.
     */
    public function telemetry(bool $fresh = false): Telemetry
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        $seconds = max(1, (int) config('blynk.cache_seconds', 1));

        $payload = Cache::remember(
            self::CACHE_KEY,
            now()->addSeconds($seconds),
            fn (): array => $this->fetch()->toArray(),
        );

        return Telemetry::fromArray($payload);
    }

    public function setBuzzer(bool $enabled): void
    {
        $this->write('buzzer', $enabled ? 1 : 0);
    }

    public function calibrate(): void
    {
        $this->write('calibrate', 1);
    }

    /**
     * Write a single control datastream. Only pins configured under
     * blynk.controls can ever be written.
     */
    private function write(string $control, int|string $value): void
    {
        if (! $this->configured()) {
            throw BlynkException::notConfigured();
        }

        $pin = config("blynk.controls.{$control}");

        if (blank($pin)) {
            throw BlynkException::rejected("Kontrol [{$control}] tidak dikonfigurasi.");
        }

        try {
            $response = Http::timeout($this->timeout())
                ->acceptJson()
                ->get($this->endpointUrl('update').'?'.http_build_query([
                    'token' => config('blynk.token'),
                    $pin => $value,
                ]));
        } catch (ConnectionException $exception) {
            throw BlynkException::unreachable($exception->getMessage());
        }

        if ($response->failed()) {
            throw BlynkException::rejected($this->controlErrorMessage($response, (string) $pin));
        }

        Cache::forget(self::CACHE_KEY);
    }

    private function fetch(): Telemetry
    {
        if (! $this->configured()) {
            return Telemetry::unavailable('Token Blynk belum diatur di server.', configured: false);
        }

        try {
            $responses = Http::pool(fn (Pool $pool): array => [
                $pool->as('pins')
                    ->timeout($this->timeout())
                    ->acceptJson()
                    ->get($this->pinsUrl()),
                $pool->as('online')
                    ->timeout($this->timeout())
                    ->get($this->endpointUrl('isHardwareConnected').'?'.http_build_query([
                        'token' => config('blynk.token'),
                    ])),
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('Blynk request failed', ['reason' => $exception->getMessage()]);

            return Telemetry::unavailable('Perangkat tidak dapat dihubungi.');
        }

        $pinsResponse = $responses['pins'] ?? null;
        $onlineResponse = $responses['online'] ?? null;

        if (! $pinsResponse instanceof Response) {
            return Telemetry::unavailable('Respons Blynk tidak valid.');
        }

        if ($pinsResponse->failed()) {
            Log::warning('Blynk rejected pin read', ['status' => $pinsResponse->status()]);

            return Telemetry::unavailable($this->errorMessage($pinsResponse));
        }

        $online = $onlineResponse instanceof Response
            && $onlineResponse->successful()
            && trim($onlineResponse->body()) === 'true';

        $values = $this->parseValues($pinsResponse);

        $pin = fn (string $field): ?float => $values[config("blynk.pins.{$field}")] ?? null;

        return new Telemetry(
            configured: true,
            online: $online,
            temperatureHot: $pin('temperature_hot'),
            temperatureCold: $pin('temperature_cold'),
            deltaTemperature: $pin('delta_temperature'),
            voltage: $pin('voltage'),
            current: $pin('current'),
            power: $pin('power'),
            statusCode: $this->parseStatusCode($pin('status')),
        );
    }

    /**
     * @return array<string, float>
     */
    private function parseValues(Response $response): array
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            $numeric = trim($response->body());

            return is_numeric($numeric) ? ['value' => (float) $numeric] : [];
        }

        $values = [];

        foreach ($payload as $pin => $value) {
            if (is_numeric($value)) {
                $values[(string) $pin] = (float) $value;
            }
        }

        return $values;
    }

    private function parseStatusCode(?float $status): ?int
    {
        if ($status === null) {
            return null;
        }

        $code = (int) round($status);

        return array_key_exists($code, config('blynk.status')) ? $code : null;
    }

    private function pinsUrl(): string
    {
        $pins = implode('&', array_map('rawurlencode', array_values(config('blynk.pins'))));

        return $this->endpointUrl('get').'?'.$pins.'&'.http_build_query(['token' => config('blynk.token')]);
    }

    private function endpointUrl(string $endpoint): string
    {
        return rtrim(config('blynk.base_url'), '/').'/'.ltrim($endpoint, '/');
    }

    private function timeout(): int
    {
        return max(1, (int) config('blynk.timeout', 8));
    }

    private function errorMessage(Response $response): string
    {
        $message = data_get($response->json(), 'error.message');

        if (is_string($message) && $message !== '') {
            return $message;
        }

        return 'Blynk menolak permintaan (HTTP '.$response->status().').';
    }

    private function controlErrorMessage(Response $response, string $pin): string
    {
        $message = $this->errorMessage($response);

        if (str_contains($message, "doesn't exist in the product")) {
            return "Datastream kontrol {$pin} belum tersedia di produk Blynk.";
        }

        return $message;
    }

    /**
     * Safe accessor for tests and diagnostics.
     */
    public function forgetSnapshot(): void
    {
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // Cache is best effort; never break the dashboard for it.
        }
    }
}
