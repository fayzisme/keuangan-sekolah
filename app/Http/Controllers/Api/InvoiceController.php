<?php

namespace App\Http\Controllers\Api;

use App\Domain\Billing\Actions\GenerateInvoicesAction;
use App\Domain\Billing\Actions\VoidInvoiceAction;
use App\Domain\Billing\Models\BillingInvoice;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateInvoicesRequest;
use App\Http\Requests\VoidInvoiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InvoiceController extends Controller
{
    private function scoped(Request $request)
    {
        return BillingInvoice::query()->where('school_id', $request->attributes->get('school_id'));
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->scoped($request)->with(['student:id,name,nis', 'billType:id,name,tipe_bayar']);

        if ($studentId = $request->get('student_id')) {
            $query->where('student_id', $studentId);
        }

        if ($billTypeId = $request->get('bill_type_id')) {
            $query->where('bill_type_id', $billTypeId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($periodeTahun = $request->get('periode_tahun')) {
            $query->where('periode_tahun', $periodeTahun);
        }

        if ($periodeBulan = $request->get('periode_bulan')) {
            $query->where('periode_bulan', $periodeBulan);
        }

        if ($search = $request->get('search')) {
            $query->whereHas('student', fn ($q) => $q->where('name', 'ilike', "%{$search}%"));
        }

        $perPage = min((int) ($request->get('per_page') ?? 20), 100);

        return response()->json(
            $query->orderByDesc('id')->simplePaginate($perPage)
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->scoped($request)->with('student:id,name,nis')->findOrFail($id));
    }

    public function generate(GenerateInvoicesRequest $request, GenerateInvoicesAction $action): JsonResponse
    {
        $dto = array_merge($request->validated(), [
            'school_id' => $request->attributes->get('school_id'),
        ]);

        $result = $action($dto);

        return response()->json($result, 200);
    }

    public function void(VoidInvoiceRequest $request, VoidInvoiceAction $action, int $id): JsonResponse
    {
        $invoice = $this->scoped($request)->findOrFail($id);

        try {
            $action($invoice);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['message' => 'Invoice di-void.', 'invoice' => $invoice->fresh()], 200);
    }
}
