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

final class BillingInvoice extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'invoices';

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_PARTIAL = 'PARTIAL';
    public const STATUS_PAID = 'PAID';
    public const STATUS_VOID = 'VOID';

    protected $fillable = [
        'school_id',
        'student_id',
        'bill_type_id',
        'academic_year_id',
        'periode_bulan',
        'periode_tahun',
        'amount_cents',
        'status',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'due_at' => 'date',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function billType(): BelongsTo
    {
        return $this->belongsTo(BillType::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'invoice_id');
    }
}
