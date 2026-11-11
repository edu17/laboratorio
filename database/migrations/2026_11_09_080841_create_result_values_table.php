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
        Schema::create('result_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_exam_id')->constrained()->restrictOnDelete();
            $table->string('parameter_code', 120);
            $table->json('value')->nullable();
            $table->foreignId('edited_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['order_exam_id', 'parameter_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_values');
    }
};
