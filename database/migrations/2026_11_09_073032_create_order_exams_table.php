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
        Schema::create('order_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_catalog_version_id')->nullable()->constrained('exam_catalog_versions')->restrictOnDelete();
            $table->json('catalog_snapshot');
            $table->string('status', 30)->default('pendiente');
            $table->timestamps();
            $table->unique(['order_id', 'exam_id']);
            $table->index(['status', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_exams');
    }
};
