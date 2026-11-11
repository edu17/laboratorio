<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Nombre completo sin tildes ni mayúsculas, para que la búsqueda y la detección de duplicados funcionen
 * igual en cualquier base de datos ("jose perez" encuentra a "José Pérez") y con índice.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->string('search_name', 310)->nullable()->after('last_names')->index();
        });

        DB::table('patients')->orderBy('id')->chunkById(500, function ($patients): void {
            foreach ($patients as $patient) {
                DB::table('patients')->where('id', $patient->id)->update([
                    'search_name' => Str::of($patient->first_names.' '.$patient->last_names)->ascii()->lower()->squish()->toString(),
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->dropIndex(['search_name']);
            $table->dropColumn('search_name');
        });
    }
};
