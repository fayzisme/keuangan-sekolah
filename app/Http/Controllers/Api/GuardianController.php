<?php

namespace App\Http\Controllers\Api;

use App\Domain\Student\Actions\CreateGuardianAction;
use App\Domain\Student\Models\Guardian;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuardianRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GuardianController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schoolId = $request->attributes->get('school_id');

        // Guardian milik sekolah = guardian yang terhubung ke murid sekolah tsb.
        $query = Guardian::query()
            ->whereHas('students', fn ($q) => $q->where('school_id', $schoolId));

        if ($search = $request->get('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $perPage = min((int) ($request->get('per_page') ?? 20), 100);

        return response()->json(
            $query->orderBy('name')->simplePaginate($perPage)
        );
    }

    public function store(GuardianRequest $request, CreateGuardianAction $action): JsonResponse
    {
        return response()->json($action($request->validated()), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $schoolId = $request->attributes->get('school_id');

        return response()->json(
            Guardian::query()
                ->whereHas('students', fn ($q) => $q->where('school_id', $schoolId))
                ->with('students:id,name,nis')
                ->findOrFail($id)
        );
    }

    public function update(GuardianRequest $request, int $id): JsonResponse
    {
        $schoolId = $request->attributes->get('school_id');

        $guardian = Guardian::query()
            ->whereHas('students', fn ($q) => $q->where('school_id', $schoolId))
            ->findOrFail($id);

        $guardian->update($request->validated());

        return response()->json($guardian->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $schoolId = $request->attributes->get('school_id');

        // Soft delete: tidak putus referensi historis.
        Guardian::query()
            ->whereHas('students', fn ($q) => $q->where('school_id', $schoolId))
            ->findOrFail($id)
            ->delete();

        return response()->json(null, 204);
    }
}
