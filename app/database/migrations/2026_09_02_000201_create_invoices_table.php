<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('periode_bulan')->nullable();
            $table->unsignedSmallInteger('periode_tahun');
            $table->unsignedBigInteger('amount_cents');
            $table->string('status')->default('OPEN');
            $table->date('due_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('school_id');
            $table->index('student_id');
            $table->index('bill_type_id');
            $table->index('academic_year_id');
            $table->index(['periode_tahun', 'periode_bulan']);
        });

        // Anti double-invoice. Include academic_year_id because GenerateInvoicesAction
        // uses it in createOrFirst identity; unique index and lookup identity must match.
        DB::unprepared('
            CREATE UNIQUE INDEX invoices_monthly_unique
            ON invoices (school_id, student_id, bill_type_id, academic_year_id, periode_bulan, periode_tahun)
            WHERE periode_bulan IS NOT NULL AND deleted_at IS NULL;
        ');

        DB::unprepared('
            CREATE UNIQUE INDEX invoices_onetime_unique
            ON invoices (school_id, student_id, bill_type_id, academic_year_id, periode_tahun)
            WHERE periode_bulan IS NULL AND deleted_at IS NULL;
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
