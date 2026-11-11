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
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('validation_version')->default(0);
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('validated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['validated_by']);
            $table->dropColumn(['validated_by', 'validated_at', 'validation_version']);
        });
    }
};
