<?php

namespace App\Http\Controllers\Api;

use App\Domain\Billing\Actions\CreateBillTypeAction;
use App\Domain\Billing\Actions\UpdateBillTypeAction;
use App\Domain\Billing\Models\BillType;
use App\Http\Controllers\Controller;
use App\Http\Requests\BillTypeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BillTypeController extends Controller
{
    private function scoped(Request $request)
    {
        return BillType::query()->where('school_id', $request->attributes->get('school_id'));
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->scoped($request);

        if ($search = $request->get('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $perPage = min((int) ($request->get('per_page') ?? 20), 100);

        return response()->json(
            $query->orderBy('name')->simplePaginate($perPage)
        );
    }

    public function store(BillTypeRequest $request, CreateBillTypeAction $action): JsonResponse
    {
        $billType = $action($request->attributes->get('school_id'), $request->validated());

        return response()->json($billType, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->scoped($request)->findOrFail($id));
    }

    public function update(BillTypeRequest $request, UpdateBillTypeAction $action, int $id): JsonResponse
    {
        $billType = $this->scoped($request)->findOrFail($id);
        $action($billType, $request->validated());

        return response()->json($billType->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->scoped($request)->findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
