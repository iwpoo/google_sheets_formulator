<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Предоставленные учетные данные неверны.'
            ], 401);
        }

        $user->tokens->each(static function($token): void { // удалим все старые токены
            $token->delete();
        });
        $token = $user->createToken('token-name')->plainTextToken;

        return response()->json(['token' => $token]);
    }
}
