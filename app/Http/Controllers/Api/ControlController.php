<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Blynk\BlynkClient;
use App\Services\Blynk\BlynkException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ControlController extends Controller
{
    public function buzzer(Request $request, BlynkClient $blynk): JsonResponse
    {
        if (! $blynk->controlsEnabled()) {
            return $this->disabled();
        }

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        try {
            $blynk->setBuzzer((bool) $validated['enabled']);
        } catch (BlynkException $exception) {
            return $this->failure($exception);
        }

        Log::info('Buzzer command sent', [
            'enabled' => (bool) $validated['enabled'],
            'ip' => $request->ip(),
        ]);

        return response()->json(['ok' => true, 'enabled' => (bool) $validated['enabled']]);
    }

    public function calibrate(Request $request, BlynkClient $blynk): JsonResponse
    {
        if (! $blynk->controlsEnabled()) {
            return $this->disabled();
        }

        try {
            $blynk->calibrate();
        } catch (BlynkException $exception) {
            return $this->failure($exception);
        }

        Log::info('INA219 calibration command sent', ['ip' => $request->ip()]);

        return response()->json(['ok' => true]);
    }

    private function failure(BlynkException $exception): JsonResponse
    {
        Log::warning('Control command failed', ['reason' => $exception->getMessage()]);

        return response()->json([
            'ok' => false,
            'message' => $exception->getMessage(),
        ], 422);
    }

    private function disabled(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Kontrol perangkat sedang dinonaktifkan.',
        ], 409);
    }
}
