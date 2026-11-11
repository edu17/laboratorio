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
            $table->string('technical_signature_path')->nullable();
            $table->string('biologist_signature_path')->nullable();
            $table->string('institutional_seal_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lab_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'technical_signature_path',
                'biologist_signature_path',
                'institutional_seal_path',
            ]);
        });
    }
};
