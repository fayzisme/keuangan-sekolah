<?php

namespace App\Http\Controllers\Api;

use App\Domain\Student\Actions\AttachGuardianAction;
use App\Domain\Student\Actions\CreateStudentAction;
use App\Domain\Student\Actions\UpdateStudentAction;
use App\Domain\Student\Models\Student;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttachGuardianRequest;
use App\Http\Requests\StudentRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StudentController extends Controller
{
    private function scoped(Request $request): Builder
    {
        return Student::query()->where('school_id', $request->attributes->get('school_id'));
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->scoped($request)->with('classRoom:id,name');

        if ($classId = $request->get('class_id')) {
            $query->where('class_id', $classId);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOL));
        }

        if ($search = $request->get('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('nis', 'ilike', "%{$search}%");
            });
        }

        $perPage = min((int) ($request->get('per_page') ?? 20), 100);

        return response()->json(
            $query->orderBy('name')->simplePaginate($perPage)
        );
    }

    public function store(StudentRequest $request, CreateStudentAction $action): JsonResponse
    {
        $student = $action($request->attributes->get('school_id'), $request->validated());

        return response()->json($student, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->scoped($request)->with(['classRoom:id,name', 'guardians'])->findOrFail($id)
        );
    }

    public function update(StudentRequest $request, UpdateStudentAction $action, int $id): JsonResponse
    {
        $student = $this->scoped($request)->findOrFail($id);
        $action($student, $request->validated());

        return response()->json($student->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->scoped($request)->findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    public function attachGuardian(AttachGuardianRequest $request, AttachGuardianAction $action, int $id): JsonResponse
    {
        $student = $this->scoped($request)->findOrFail($id);

        // Cegah duplikat: re-attach guardian yang sama = idempoten (metadata di-update).
        if ($student->guardians()->where('guardians.id', $request->guardian_id)->exists()) {
            $student->guardians()->updateExistingPivot($request->guardian_id, [
                'relation' => $request->get('relation', 'wali'),
                'is_primary' => (bool) $request->get('is_primary', false),
            ]);
        } else {
            $action($student, (int) $request->guardian_id, $request->get('relation', 'wali'), (bool) $request->get('is_primary', false));
        }

        return response()->json(['message' => 'Guardian ditautkan.'], 200);
    }

    public function detachGuardian(Request $request, int $id, int $guardianId): JsonResponse
    {
        $student = $this->scoped($request)->findOrFail($id);
        $student->guardians()->detach($guardianId);

        return response()->json(null, 204);
    }
}
