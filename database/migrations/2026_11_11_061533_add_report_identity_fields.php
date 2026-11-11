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
        Schema::table('lab_profiles', function (Blueprint $table): void {
            $table->string('sanitary_registration', 100)->nullable();
            $table->string('emergency_phone', 40)->nullable();
            $table->string('opening_hours', 255)->nullable();
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('referring_doctor', 180)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_profiles', function (Blueprint $table): void {
            $table->dropColumn(['sanitary_registration', 'emergency_phone', 'opening_hours']);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('referring_doctor');
        });
    }
};
