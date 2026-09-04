<?php

namespace App\Http\Controllers\Api;

use App\Domain\Billing\Actions\CreateManualPaymentAction;
use App\Domain\Billing\Actions\ProcessManualPaymentAction;
use App\Domain\Billing\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreManualPaymentRequest;
use App\Http\Requests\VerifyPaymentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

final class PaymentController extends Controller
{
    private function scoped(Request $request)
    {
        return Payment::query()->where('school_id', $request->attributes->get('school_id'));
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->scoped($request)
            ->with(['invoices:id,student_id,amount_cents', 'creator:id,name', 'verifier:id,name']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($method = $request->get('method')) {
            $query->where('method', $method);
        }

        $perPage = min((int) ($request->get('per_page') ?? 20), 100);

        return response()->json(
            $query->orderByDesc('id')->simplePaginate($perPage)
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(
            $this->scoped($request)
                ->with(['invoices:id,student_id,amount_cents', 'creator:id,name', 'verifier:id,name', 'receipt'])
                ->findOrFail($id)
        );
    }

    public function store(StoreManualPaymentRequest $request, CreateManualPaymentAction $action): JsonResponse
    {
        $data = array_merge($request->validated(), [
            'school_id' => $request->attributes->get('school_id'),
            'created_by' => $request->user()->id,
        ]);

        $payment = $action($data);

        return response()->json($payment->load('invoices'), 201);
    }

    public function verify(VerifyPaymentRequest $request, ProcessManualPaymentAction $action, int $id): JsonResponse
    {
        // Ensure school context for the payment
        $schoolId = $request->attributes->get('school_id');
        $payment = Payment::query()
            ->where('school_id', $schoolId)
            ->whereKey($id)
            ->firstOrFail();

        // Maker-checker: creator != verifier
        if ($payment->created_by === $request->user()->id) {
            return response()->json([
                'message' => 'maker-checker: pencatat tidak boleh memverifikasi pembayaran sendiri.',
            ], 409);
        }

        if ($payment->status !== Payment::STATUS_PENDING_VERIFICATION) {
            return response()->json([
                'message' => "Payment {$id} bukan PENDING_VERIFICATION (status={$payment->status}).",
            ], 409);
        }

        try {
            $payment = $action($payment, $request->user()->id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($payment->load('invoices', 'receipt'), 200);
    }
}
