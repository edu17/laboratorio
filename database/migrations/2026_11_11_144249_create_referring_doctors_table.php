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
        Schema::create('referring_doctors', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 180)->unique();
            $table->string('kind', 20)->default('medico');
            $table->string('specialty', 120)->nullable();
            $table->string('license_number', 40)->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referring_doctors');
    }
};
