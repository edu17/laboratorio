<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaza las órdenes con el catálogo de médicos remitentes. Los nombres que ya estaban escritos en
 * órdenes existentes pasan al catálogo y se enlazan; el texto de la orden se conserva tal cual.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('referring_doctor_id')->nullable()->after('referring_doctor')->constrained()->restrictOnDelete();
        });

        $this->linkExistingReferrals();
    }

    /**
     * Pasa al catálogo los nombres escritos a mano en órdenes sin médico enlazado y los enlaza,
     * sin distinguir mayúsculas ni espacios repetidos.
     */
    public function linkExistingReferrals(): void
    {
        $names = DB::table('orders')
            ->whereNull('referring_doctor_id')
            ->whereNotNull('referring_doctor')
            ->where('referring_doctor', '!=', '')
            ->distinct()
            ->pluck('referring_doctor');

        foreach ($names as $name) {
            $normalizedName = trim(preg_replace('/\s+/u', ' ', $name));

            if ($normalizedName === '') {
                continue;
            }

            $doctorId = DB::table('referring_doctors')->whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedName)])->value('id')
                ?? DB::table('referring_doctors')->insertGetId([
                    'name' => mb_substr($normalizedName, 0, 180),
                    'kind' => 'medico',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('orders')->whereNull('referring_doctor_id')->where('referring_doctor', $name)->update(['referring_doctor_id' => $doctorId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // En SQLite quitar la columna reconstruye la tabla; con órdenes que tienen exámenes,
        // las claves foráneas lo impedirían si siguieran activas durante la operación.
        Schema::withoutForeignKeyConstraints(function (): void {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('referring_doctor_id');
            });
        });
    }
};
