<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige "EXÁMEN DE HECES" (tilde sobrante) a "Examen de heces".
 *
 * Las versiones publicadas del catálogo no se modifican: si el examen tiene versiones, se publica una
 * nueva con el nombre corregido para que las órdenes nuevas lo usen. Las órdenes existentes conservan
 * su propia copia, que es el registro histórico.
 */
return new class extends Migration
{
    private const OLD_NAME = 'EXÁMEN DE HECES';

    private const NEW_NAME = 'Examen de heces';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->rename(self::OLD_NAME, self::NEW_NAME);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->rename(self::NEW_NAME, self::OLD_NAME);
    }

    private function rename(string $from, string $to): void
    {
        DB::transaction(function () use ($from, $to): void {
            $exam = DB::table('exams')->where('code', 'HECES')->where('name', $from)->first();

            if (! $exam) {
                return;
            }

            DB::table('exams')->where('id', $exam->id)->update(['name' => $to, 'updated_at' => now()]);

            $latestVersion = DB::table('exam_catalog_versions')
                ->where('exam_id', $exam->id)
                ->orderByDesc('version_number')
                ->first();

            if (! $latestVersion) {
                return;
            }

            $snapshot = json_decode($latestVersion->snapshot, true);

            if (($snapshot['exam']['name'] ?? null) !== $from) {
                return;
            }

            $snapshot['exam']['name'] = $to;

            DB::table('exam_catalog_versions')->insert([
                'exam_id' => $exam->id,
                'version_number' => $latestVersion->version_number + 1,
                'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'created_by' => null,
                'published_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
};
