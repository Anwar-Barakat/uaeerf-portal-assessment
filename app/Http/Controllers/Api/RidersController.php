<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Soap\RidersService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RidersController extends Controller
{
    public function __construct(
        protected RidersService $ridersService
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        try {
            $riders = $this->ridersService->searchByName($validated['q']);

            return response()->json([
                'success' => true,
                'data' => $riders,
            ]);
        } catch (\Exception $e) {
            Log::error('Rider search failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to search riders',
            ], 500);
        }
    }
}
