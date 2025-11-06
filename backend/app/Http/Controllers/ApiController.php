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
            // Log the attempt
            Log::info('VAPID key request received');

            // Try to get the key from config
            $publicKey = '';
            try {
                $publicKey = trim((string) config('webpush.vapid.public_key', ''));
                Log::info('VAPID key retrieved from config', [
                    'key_length' => strlen($publicKey),
                    'key_preview' => $publicKey ? substr($publicKey, 0, 20) . '...' : 'empty',
                ]);
            } catch (\Exception $configException) {
                Log::warning('Error reading VAPID config: ' . $configException->getMessage(), [
                    'exception' => get_class($configException),
                    'file' => $configException->getFile(),
                    'line' => $configException->getLine(),
                ]);
                // Continue with empty key
            }

            // Also try to get from env directly as fallback
            if ($publicKey === '') {
                try {
                    $publicKey = trim((string) env('VAPID_PUBLIC_KEY', ''));
                    if ($publicKey !== '') {
                        Log::info('VAPID key retrieved from env directly');
                    }
                } catch (\Exception $envException) {
                    Log::warning('Error reading VAPID from env: ' . $envException->getMessage());
                }
            }

            // Always return 200, even if key is empty
            // Frontend will handle this gracefully
            return response()->json([
                'vapid_public_key' => $publicKey,
                'configured' => $publicKey !== '',
                'message' => $publicKey === '' 
                    ? 'VAPID public key is not configured. Push notifications will be disabled.'
                    : 'VAPID key retrieved successfully',
            ], 200);
        } catch (\Throwable $e) {
            // Catch any fatal errors or exceptions
            Log::error('Fatal error getting VAPID key: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Always return 200 with empty key to prevent frontend errors
            return response()->json([
                'vapid_public_key' => '',
                'configured' => false,
                'message' => 'Error retrieving VAPID key.',
            ], 200);
        }
    }
}
