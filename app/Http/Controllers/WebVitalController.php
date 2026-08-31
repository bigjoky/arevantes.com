<?php

namespace App\Http\Controllers;

use App\Models\WebVitalSample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WebVitalController extends Controller
{
    public function store(Request $request): Response|JsonResponse
    {
        $maximum = $request->input('metric') === 'CLS' ? 10 : 120000;
        $validator = Validator::make($request->all(), [
            'route_name' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9_.\/-]+$/'],
            'metric' => ['required', Rule::in(['LCP', 'INP', 'CLS'])],
            'value' => ['required', 'numeric', 'min:0', "max:{$maximum}"],
            'delta' => ['required', 'numeric', 'min:0', "max:{$maximum}"],
            'device' => ['required', Rule::in(['mobile', 'tablet', 'desktop'])],
            'navigation_type' => ['required', Rule::in(['navigate', 'reload', 'back-forward', 'back-forward-cache', 'prerender', 'restore', 'soft-navigation'])],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => __('The given data was invalid.'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        WebVitalSample::create([
            ...$data,
            'rating' => $this->rating($data['metric'], (float) $data['value']),
            'release' => config('performance.release'),
            'occurred_at' => now(),
        ]);

        return response()->noContent();
    }

    private function rating(string $metric, float $value): string
    {
        [$good, $poor] = match ($metric) {
            'LCP' => [2500, 4000],
            'INP' => [200, 500],
            'CLS' => [0.1, 0.25],
        };

        return match (true) {
            $value <= $good => 'good',
            $value <= $poor => 'needs-improvement',
            default => 'poor',
        };
    }
}
