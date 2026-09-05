<?php

namespace App\Domain\Billing\Models;

use App\Domain\School\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Payment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'school_id',
        'created_by',
        'method',
        'status',
        'total_cents',
        'proof_path',
        'cashier_name',
        'gateway_trx_id',
        'verified_by',
        'verified_at',
    ];

    public const METHOD_CASH = 'CASH';

    public const METHOD_SNAP = 'SNAP';

    public const STATUS_PENDING_VERIFICATION = 'PENDING_VERIFICATION';

    public const STATUS_SETTLED = 'SETTLED';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_REFUNDED = 'REFUNDED';

    protected function casts(): array
    {
        return [
            'total_cents' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(BillingInvoice::class, 'payment_invoice')
            ->withPivot('allocated_cents')
            ->withTimestamps();
    }

    public function ledgerEntry(): MorphOne
    {
        return $this->morphOne(LedgerEntry::class, 'ref');
    }

    public function receipt(): MorphOne
    {
        return $this->morphOne(Receipt::class, 'payment');
    }
}
