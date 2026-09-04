<?php

namespace App\Domain\Auth\Actions;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class LoginAction
{
    public function __invoke(LoginRequest $request): array
    {
        $user = User::query()->where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan tidak cocok dengan catatan kami.'],
            ]);
        }

        $schools = $user->schools()->withPivot('is_active')->get();
        $activeSchool = $user->activeSchool();

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'schools' => $schools->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'is_active' => (bool) $s->pivot->is_active,
            ]),
            'active_school' => $activeSchool ? ['id' => $activeSchool->id, 'name' => $activeSchool->name] : null,
        ];
    }
}
