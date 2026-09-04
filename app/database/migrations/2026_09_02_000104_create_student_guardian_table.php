<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_guardian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->string('relation')->default('wali'); // ayah | ibu | wali
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['student_id', 'guardian_id']);
            $table->index('guardian_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_guardian');
    }
};
