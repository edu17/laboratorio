<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lab_profiles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('version')->unique();
            $table->string('institution_name', 180);
            $table->string('subtitle', 255)->nullable();
            $table->text('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('order_prefix', 12)->default('LAB');
            $table->unsignedTinyInteger('order_number_padding')->default(6);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->index('is_active');
        });

        DB::table('lab_profiles')->insert([
            'version' => 1,
            'institution_name' => 'LABORATORIO CLÍNICO-BIOLÓGICO ISMALEJ PRADO',
            'subtitle' => 'Reporte de resultados de laboratorio',
            'order_prefix' => 'LAB',
            'order_number_padding' => 6,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_profiles');
    }
};
