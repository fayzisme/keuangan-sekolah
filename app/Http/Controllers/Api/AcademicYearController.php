<?php

namespace App\Http\Controllers\Api;

use App\Domain\School\Actions\CreateAcademicYearAction;
use App\Domain\School\Actions\UpdateAcademicYearAction;
use App\Domain\School\Models\AcademicYear;
use App\Http\Controllers\Controller;
use App\Http\Requests\AcademicYearRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AcademicYearController extends Controller
{
    private function scoped(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return AcademicYear::query()->where('school_id', $request->attributes->get('school_id'));
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->scoped($request);

        if ($search = $request->get('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $perPage = min((int) ($request->get('per_page') ?? 20), 100);

        return response()->json(
            $query->orderByDesc('id')->simplePaginate($perPage)
        );
    }

    public function store(AcademicYearRequest $request, CreateAcademicYearAction $action): JsonResponse
    {
        $year = $action($request->attributes->get('school_id'), $request->validated());

        return response()->json($year, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $year = $this->scoped($request)->findOrFail($id);

        return response()->json($year);
    }

    public function update(AcademicYearRequest $request, UpdateAcademicYearAction $action, int $id): JsonResponse
    {
        $year = $this->scoped($request)->findOrFail($id);
        $action($year, $request->validated());

        return response()->json($year->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $year = $this->scoped($request)->findOrFail($id);
        $year->delete();

        return response()->json(null, 204);
    }
}
