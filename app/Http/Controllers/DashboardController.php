<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'refreshMs' => 2000,
            'device' => config('blynk.device'),
            'controlsEnabled' => (bool) config('blynk.controls_enabled', true),
        ]);
    }
}
