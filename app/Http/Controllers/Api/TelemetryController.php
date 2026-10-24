<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Blynk\BlynkClient;
use Illuminate\Http\JsonResponse;

class TelemetryController extends Controller
{
    public function __invoke(BlynkClient $blynk): JsonResponse
    {
        $telemetry = $blynk->telemetry();

        return response()
            ->json($telemetry->toArray())
            ->header('Cache-Control', 'no-store');
    }
}
