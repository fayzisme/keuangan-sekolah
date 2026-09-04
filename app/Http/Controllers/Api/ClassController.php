<?php

namespace App\Http\Controllers\Api;

use App\Domain\School\Actions\CreateClassAction;
use App\Domain\School\Actions\UpdateClassAction;
use App\Domain\School\Models\ClassRoom;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClassRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClassController extends Controller
{
    private function scoped(Request $request): Builder
    {
        return ClassRoom::query()->where('school_id', $request->attributes->get('school_id'));
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->scoped($request);

        if ($yearId = $request->get('academic_year_id')) {
            $query->where('academic_year_id', $yearId);
        }

        if ($search = $request->get('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $perPage = min((int) ($request->get('per_page') ?? 20), 100);

        return response()->json(
            $query->with('academicYear:id,name,semester')->orderBy('name')->simplePaginate($perPage)
        );
    }

    public function store(ClassRequest $request, CreateClassAction $action): JsonResponse
    {
        $class = $action($request->attributes->get('school_id'), $request->validated());

        return response()->json($class, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->scoped($request)->with('academicYear:id,name,semester')->findOrFail($id));
    }

    public function update(ClassRequest $request, UpdateClassAction $action, int $id): JsonResponse
    {
        $class = $this->scoped($request)->findOrFail($id);
        $action($class, $request->validated());

        return response()->json($class->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->scoped($request)->findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
