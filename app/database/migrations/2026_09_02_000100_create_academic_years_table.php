<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('semester')->default('ganjil');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('school_id');
        });

        DB::unprepared('
            CREATE UNIQUE INDEX academic_years_school_name_semester_live_unique
            ON academic_years (school_id, name, semester)
            WHERE deleted_at IS NULL;
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
