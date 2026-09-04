<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('number'); // format: RECEIPT-{year}-{seq}
            $table->timestamps();

            $table->unique(['school_id', 'academic_year_id', 'number']);
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
