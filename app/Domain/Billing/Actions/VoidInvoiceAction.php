<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Models\BillingInvoice;
use RuntimeException;

final class VoidInvoiceAction
{
    public function __invoke(BillingInvoice $invoice): BillingInvoice
    {
        if ($invoice->status !== BillingInvoice::STATUS_OPEN) {
            throw new RuntimeException(
                sprintf('Invoice %d hanya bisa di-void bila status OPEN (sekarang=%s).', $invoice->id, $invoice->status)
            );
        }

        $invoice->update(['status' => BillingInvoice::STATUS_VOID]);

        return $invoice->fresh();
    }
}
