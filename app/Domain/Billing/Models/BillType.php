<?php

namespace App\Domain\Billing\Models;

use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\School;
use App\Domain\Student\Models\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class BillType extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'school_id',
        'name',
        'tipe_bayar',       // monthly | one_time
        'tarif_cents',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tarif_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(BillingInvoice::class, 'bill_type_id');
    }

    /**
     * Hitung tarif untuk murid & tahun ajaran tertentu.
     * Base implementation = tarif_cents flat.
     * Subclass/extension bisa override utk diskon/dll.
     */
    public function amountFor(Student $student, AcademicYear $year): int
    {
        return (int) $this->tarif_cents;
    }
}
