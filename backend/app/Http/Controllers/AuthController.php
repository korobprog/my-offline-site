<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);
    
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => User::ROLE_USER,
        ]);
    
        $token = $user->createToken('auth_token')->plainTextToken;
    
        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Пользователь успешно зарегистрирован',
        ], 201);
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            Log::info('Login attempt', ['email' => $request->email]);

            // Check database connection first
            try {
                DB::connection()->getPdo();
            } catch (\Exception $dbException) {
                Log::error('Database connection error during login: ' . $dbException->getMessage(), [
                    'exception' => get_class($dbException),
                    'trace' => $dbException->getTraceAsString(),
                ]);
                
                return response()->json([
                    'message' => 'Ошибка подключения к базе данных. Обратитесь к администратору.',
                    'error' => app()->environment('local') ? $dbException->getMessage() : null,
                ], 500);
            }

            $user = User::where('email', $request->email)->first();

            if (!$user) {
                Log::warning('Login failed: User not found', ['email' => $request->email]);
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            if (!Hash::check($request->password, $user->password)) {
                Log::warning('Login failed: Invalid password', ['email' => $request->email]);
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            Log::info('Login successful', ['email' => $request->email, 'user_id' => $user->id]);

            return response()->json([
                'user' => $user,
                'token' => $token,
                'message' => 'Вход выполнен успешно',
            ], 200);
        } catch (ValidationException $e) {
            // Re-throw validation exceptions
            throw $e;
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'email' => $request->email ?? null,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'exception_class' => get_class($e),
            ]);
            
            // Check if it's a database-related error
            $isDbError = str_contains($e->getMessage(), 'PDO') || 
                        str_contains($e->getMessage(), 'database') ||
                        str_contains($e->getMessage(), 'SQLSTATE');
            
            return response()->json([
                'message' => $isDbError 
                    ? 'Ошибка подключения к базе данных. Обратитесь к администратору.'
                    : 'Произошла ошибка при входе',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'message' => 'Выход выполнен успешно',
        ], 200);
    }

    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ], 200);
    }
}
