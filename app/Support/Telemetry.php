<?php

namespace App\Support;

/**
 * Immutable snapshot of one CharThermCell device reading.
 */
final readonly class Telemetry
{
    public function __construct(
        public bool $configured,
        public bool $online,
        public ?float $temperatureHot = null,
        public ?float $temperatureCold = null,
        public ?float $deltaTemperature = null,
        public ?float $voltage = null,
        public ?float $current = null,
        public ?float $power = null,
        public ?int $statusCode = null,
        public ?string $error = null,
    ) {}

    public static function unavailable(string $error, bool $configured = true): self
    {
        return new self(configured: $configured, online: false, error: $error);
    }

    /**
     * Rehydrate a snapshot from its array representation (used by the cache).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $readings = $data['readings'] ?? [];
        $number = static fn (string $key): ?float => is_numeric($readings[$key] ?? null)
            ? (float) $readings[$key]
            : null;

        return new self(
            configured: (bool) ($data['configured'] ?? false),
            online: (bool) ($data['online'] ?? false),
            temperatureHot: $number('temperature_hot'),
            temperatureCold: $number('temperature_cold'),
            deltaTemperature: $number('delta_temperature'),
            voltage: $number('voltage'),
            current: $number('current'),
            power: $number('power'),
            statusCode: isset($data['status']['code']) ? (int) $data['status']['code'] : null,
            error: isset($data['error']) ? (string) $data['error'] : null,
        );
    }

    /**
     * Status code sent by the device, or one derived from the hot side
     * temperature when the device sends an unexpected value.
     */
    public function resolvedStatusCode(): int
    {
        if ($this->statusCode !== null && array_key_exists($this->statusCode, config('blynk.status'))) {
            return $this->statusCode;
        }

        if ($this->temperatureHot === null) {
            return 0;
        }

        $optimal = (float) config('blynk.thresholds.optimal');
        $safe = (float) config('blynk.thresholds.safe');

        return match (true) {
            $this->temperatureHot > $optimal => 2,
            $this->temperatureHot >= $safe => 1,
            default => 0,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $statusCode = $this->resolvedStatusCode();
        $status = config("blynk.status.{$statusCode}");

        return [
            'configured' => $this->configured,
            'online' => $this->online,
            'device' => config('blynk.device'),
            'updated_at' => now()->toIso8601String(),
            'error' => $this->error,
            'readings' => [
                'temperature_hot' => $this->round($this->temperatureHot, 1),
                'temperature_cold' => $this->round($this->temperatureCold, 1),
                'delta_temperature' => $this->round($this->deltaTemperature, 1),
                'voltage' => $this->round($this->voltage, 3),
                'current' => $this->round($this->current, 2),
                'power' => $this->round($this->power, 2),
            ],
            'status' => [
                'code' => $statusCode,
                'key' => $status['key'] ?? 'unknown',
                'label' => $status['label'] ?? 'Status tidak diketahui',
            ],
            'gauge_max' => (float) config('blynk.gauge_max'),
        ];
    }

    private function round(?float $value, int $precision): ?float
    {
        return $value === null ? null : round($value, $precision);
    }
}
