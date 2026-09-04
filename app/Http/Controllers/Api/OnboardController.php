<?php

namespace App\Http\Controllers\Api;

use App\Domain\School\Actions\OnboardSchoolAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardSchoolRequest;
use Illuminate\Http\JsonResponse;

final class OnboardController extends Controller
{
    public function store(OnboardSchoolRequest $request, OnboardSchoolAction $action): JsonResponse
    {
        $result = $action($request->validated());

        return response()->json([
            'message' => 'Sekolah berhasil di-onboard.',
            'school' => [
                'id' => $result['school']->id,
                'name' => $result['school']->name,
            ],
            'admin' => [
                'id' => $result['user']->id,
                'name' => $result['user']->name,
                'email' => $result['user']->email,
            ],
        ], 201);
    }
}
