<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('nis');
            $table->string('name');
            $table->string('gender')->nullable();
            $table->date('birth_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index('school_id');
            $table->index('class_id');
        });

        DB::unprepared('
            CREATE UNIQUE INDEX students_school_nis_live_unique
            ON students (school_id, nis)
            WHERE deleted_at IS NULL;
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
