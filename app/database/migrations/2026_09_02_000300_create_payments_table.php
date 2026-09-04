<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('method')->default('CASH'); // CASH | SNAP (fast-follow)
            $table->string('status')->default('PENDING_VERIFICATION'); // PENDING_VERIFICATION | SETTLED | FAILED | REFUNDED
            $table->unsignedBigInteger('total_cents');
            $table->string('proof_path')->nullable(); // path bukti upload
            $table->string('cashier_name')->nullable();
            $table->string('gateway_trx_id')->nullable(); // idempotency / Midtrans trx id
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique('gateway_trx_id'); // ADR-0013 idempotency
            $table->index('school_id');
            $table->index('created_by');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
