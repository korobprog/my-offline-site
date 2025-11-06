<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApiController extends Controller
{
    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        $health = [
            'status' => 'ok',
            'message' => 'Laravel API is running',
            'timestamp' => now(),
            'version' => '12.0.0',
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'php_major_version' => PHP_MAJOR_VERSION,
            'php_minor_version' => PHP_MINOR_VERSION,
        ];

        // Check database connection
        try {
            DB::connection()->getPdo();
            $health['database'] = 'connected';
        } catch (\Exception $e) {
            $health['database'] = 'disconnected';
            $health['database_error'] = app()->environment('local') ? $e->getMessage() : 'Database connection failed';
            $health['status'] = 'degraded';
        }

        // Check VAPID configuration
        $vapidKey = trim((string) config('webpush.vapid.public_key', ''));
        $health['vapid_configured'] = $vapidKey !== '';

        // Check PHP extensions
        $health['php_extensions'] = [
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'pdo_pgsql' => extension_loaded('pdo_pgsql'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
        ];

        return response()->json($health);
    }

    /**
     * Test endpoint with Laravel 12 features
     */
    public function test(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Test endpoint working',
            'data' => [
                'version' => '12.0.0',
                'environment' => app()->environment(),
                'php_version' => PHP_VERSION,
                'php_major_version' => PHP_MAJOR_VERSION,
                'php_minor_version' => PHP_MINOR_VERSION,
                'laravel_version' => app()->version(),
                'features' => [
                    'new_api' => true,
                    'php_8_4' => PHP_MAJOR_VERSION >= 8 && PHP_MINOR_VERSION >= 4
                ]
            ]
        ]);
    }

    /**
     * Get user info (requires authentication)
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
            'authenticated' => $request->user() !== null,
            'features' => []
        ]);
    }

    /**
     * Feature flags endpoint
     */
    public function features(Request $request): JsonResponse
    {
        return response()->json([
            'features' => [
                'new_api' => true,
                'php_8_4' => PHP_MAJOR_VERSION >= 8 && PHP_MINOR_VERSION >= 4,
                'laravel_12' => true
            ],
            'new_api_active' => true
        ]);
    }

    /**
     * Get VAPID public key for push notifications
     */
    public function vapidKey(): JsonResponse
    {
        try {
            $publicKey = trim((string) config('webpush.vapid.public_key', ''));

            if ($publicKey === '') {
                // Return 200 with empty key instead of 500 error
                // Frontend will handle this gracefully
                return response()->json([
                    'vapid_public_key' => '',
                    'message' => 'VAPID public key is not configured. Push notifications will be disabled.',
                ], 200);
            }

            return response()->json([
                'vapid_public_key' => $publicKey,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting VAPID key: ' . $e->getMessage());
            return response()->json([
                'vapid_public_key' => '',
                'message' => 'Error retrieving VAPID key.',
            ], 200);
        }
    }
}
