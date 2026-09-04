<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('tipe_bayar')->default('monthly'); // monthly | one_time
            $table->unsignedBigInteger('tarif_cents');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('school_id');
        });

        DB::unprepared('
            CREATE UNIQUE INDEX bill_types_school_name_live_unique
            ON bill_types (school_id, name)
            WHERE deleted_at IS NULL;
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_types');
    }
};
