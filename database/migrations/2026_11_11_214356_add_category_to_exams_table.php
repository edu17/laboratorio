<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrupa los exámenes como las hojas del libro del laboratorio; los exámenes del catálogo base reciben
     * la categoría de su archivo de origen.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('category', 40)->nullable()->after('name')->index();
        });

        $codesByCategory = [
            'heces_orina' => ['HECES', ...array_column(config('catalogo_orina.exams', []), 'code')],
            'cualitativas' => array_column(config('catalogo_cualitativos.exams', []), 'code'),
            'cuantitativas' => array_column(config('catalogo_cuantitativos.exams', []), 'code'),
        ];

        foreach ($codesByCategory as $category => $codes) {
            DB::table('exams')->whereIn('code', $codes)->update(['category' => $category]);
        }
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
