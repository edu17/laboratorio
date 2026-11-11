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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40)->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->dateTime('ordered_at');
            $table->string('status', 30)->default('pendiente');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->foreignId('canceled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'ordered_at']);
            $table->index(['patient_id', 'ordered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
