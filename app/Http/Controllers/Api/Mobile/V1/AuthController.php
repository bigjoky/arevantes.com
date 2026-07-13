<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class AuthController extends Controller
{
    public function login(Request $request, TwoFactorAuthenticationProvider $twoFactor): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
            'two_factor_code' => ['nullable', 'string', 'digits:6'],
        ]);
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => [__('The provided credentials are incorrect.')]]);
        }
        if ($user->two_factor_confirmed_at && (! isset($data['two_factor_code']) || ! $twoFactor->verify(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), $data['two_factor_code']))) {
            throw ValidationException::withMessages(['two_factor_code' => [__('A valid two-factor authentication code is required.')]]);
        }

        abort_unless($user->hasRole('admin', 'commercial', 'technician'), 403);
        $user->tokens()->where('name', $data['device_name'])->delete();

        return response()->json([
            'data' => ['token' => $user->createToken($data['device_name'], ['mobile'])->plainTextToken, 'user' => $this->user($user)],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->user($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();
        abort_unless($token && method_exists($token, 'getKey'), 401);
        $request->user()->tokens()->whereKey($token->getKey())->delete();

        return response()->json(status: 204);
    }

    /** @return array{id: int, name: string, email: string, role: string} */
    private function user(User $user): array
    {
        return $user->only(['id', 'name', 'email', 'role']);
    }
}
