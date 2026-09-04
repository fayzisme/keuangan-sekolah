<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('level')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('school_id');
            $table->index('academic_year_id');
        });

        DB::unprepared('
            CREATE UNIQUE INDEX classes_school_academic_year_name_live_unique
            ON classes (school_id, academic_year_id, name)
            WHERE deleted_at IS NULL;
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
