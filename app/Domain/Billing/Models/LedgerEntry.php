<?php

namespace App\Domain\Billing\Models;

use App\Models\User;
use App\Domain\School\Models\School;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class LedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_id',
        'ref_type',
        'ref_id',
        'debit_cents',
        'credit_cents',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'debit_cents' => 'integer',
            'credit_cents' => 'integer',
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

    public function ref(): MorphTo
    {
        return $this->morphTo();
    }
}
