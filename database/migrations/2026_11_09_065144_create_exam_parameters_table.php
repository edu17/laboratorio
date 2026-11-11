<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_section_id')->constrained()->restrictOnDelete();
            $table->string('code', 100);
            $table->string('name', 180);
            $table->string('result_type', 40)->default('text');
            $table->string('unit', 60)->nullable();
            $table->string('reference_text', 255)->nullable();
            $table->json('reference_config')->nullable();
            $table->json('options')->nullable();
            $table->string('source', 180)->nullable();
            $table->string('clinical_validation_status', 30)->default('pending');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['exam_section_id', 'code']);
            $table->index(['exam_section_id', 'is_active', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_parameters');
    }
};
