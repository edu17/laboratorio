<?php

namespace Database\Seeders;

use App\Actions\Orders\CancelLabOrder;
use App\Actions\Orders\CreateLabOrder;
use App\Actions\Orders\PublishOrderReport;
use App\Actions\Orders\ValidateLabOrder;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Models\Exam;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\Patient;
use App\Models\ReferringDoctor;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Datos ficticios para probar el sistema con un volumen parecido al real: pacientes que vuelven
 * varias veces al año, órdenes con varios exámenes y todos los estados (pendiente → publicada).
 *
 * Nunca se ejecuta con `db:seed` normal ni en producción:
 *   php artisan db:seed --class=DemoDataSeeder
 * Los pacientes ficticios tienen identificador DEMO-xxxx.
 */
class DemoDataSeeder extends Seeder
{
    private const PATIENTS = 65;

    private const ORDERS = 100;

    /** Perfiles de solicitud habituales: códigos de examen y, si aplica, análisis pedidos de un panel. */
    private const PROFILES = [
        'rutina' => ['HECES' => null, 'ORINA' => null],
        'quimica' => ['QUIMICA' => ['glucosa_pre', 'colesterol_total', 'trigliceridos', 'colesterol_hdl', 'colesterol_ldl', 'colesterol_vldl', 'creatinina']],
        'diabetico' => ['QUIMICA' => ['glucosa_pre', 'glucosa_post', 'creatinina'], 'HBA1C' => null],
        'tiroides' => ['TIROIDEAS' => ['t3', 't4', 'tsh']],
        'infeccioso' => ['DENGUE' => null, 'HEMATOCRITO_HEMOGLOBINA' => null, 'PCR' => null],
        'hepatico' => ['HEPATICAS' => null],
        'prenatal' => ['HCG_EMBARAZO' => null, 'VDRL' => null, 'VIH' => null, 'GRUPO_RH' => null, 'ORINA' => null],
        'prostata' => ['MARCADORES_TUMORALES' => ['psa_total', 'psa_libre', 'psa_relacion_libre_total']],
        'urocultivo' => ['ORINA' => null, 'UROCULTIVO' => null],
        'coagulacion' => ['COAGULACION' => null],
    ];

    /** Valores de texto plausibles por parámetro (heces, orina, urocultivo). */
    private const TEXT_VALUES = [
        'color' => ['Café', 'Amarillo', 'Marrón', 'Amarillo claro'],
        'aspecto' => ['Blando', 'Pastoso', 'Lig. Turbio', 'Transparente', 'Diarreico'],
        'restos_alimenticios' => ['+', '++', 'No se observa'],
        'ph' => ['5', '6', '6.5', '7'],
        'densidad' => ['1.010', '1.015', '1.020', '1.025'],
        'urobilinogeno' => ['0.2 mg/dL', 'Normal'],
        'sangre' => ['Negativo', 'Negativo', 'Negativo', '25 Ery/μl'],
        'celulas_epiteliales' => ['+', '++', 'Escasas'],
        'bacterias' => ['Escasas', '+', '++'],
        'cultivo_orina' => ['Sin crecimiento bacteriano a las 48 horas', 'Se aislaron más de 100,000 UFC/mL de Escherichia coli'],
        'susceptible' => ['Nitrofurantoína, Fosfomicina, Imipenem', '---'],
        'resistente' => ['Ampicilina, Trimetoprim/sulfametoxazol', '---'],
    ];

    private Generator $faker;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoDataSeeder no se ejecuta en producción.');
        }

        if (Patient::query()->where('identifier', 'like', 'DEMO-%')->exists()) {
            $this->command?->warn('Ya hay pacientes DEMO-xxxx: no se vuelven a cargar.');

            return;
        }

        $this->faker = FakerFactory::create('es_ES');
        $this->faker->seed(2026);
        mt_srand(2026);

        [$administrator, $validator] = $this->demoUsers();
        $doctors = ReferringDoctor::query()->where('is_active', true)->get();
        $exams = Exam::query()->where('is_active', true)->get()->keyBy('code');
        $patients = $this->patients();
        $visits = $this->visits($patients);
        $now = Carbon::now();

        foreach ($visits as $index => [$patient, $orderedAt]) {
            Carbon::setTestNow($orderedAt);
            [$examIds, $requested] = $this->examsFor($patient, $exams);

            $order = app(CreateLabOrder::class)->handle(
                $patient,
                $examIds,
                $administrator,
                requestedParameterCodes: $requested,
                referringDoctorId: $doctors->isNotEmpty() && mt_rand(1, 100) <= 65 ? $doctors->random()->id : null,
            );

            $ageInDays = $orderedAt->diffInDays($now);
            $this->advance($order, $administrator, $validator, $ageInDays, $index);
        }

        Carbon::setTestNow();
        $this->command?->info(sprintf('Cargados %d pacientes y %d órdenes ficticias.', $patients->count(), $visits->count()));
    }

    /**
     * Lleva la orden al estado que le corresponde según su antigüedad, como pasaría en el laboratorio.
     */
    private function advance(Order $order, User $administrator, User $validator, float $ageInDays, int $index): void
    {
        if ($ageInDays > 3 && $index % 33 === 7) {
            Carbon::setTestNow(Carbon::now()->addMinutes(30));
            app(CancelLabOrder::class)->handle($order, 'Muestra insuficiente (dato ficticio)', $administrator);

            return;
        }

        $stage = match (true) {
            $ageInDays <= 1 => mt_rand(1, 3),
            $ageInDays <= 3 => mt_rand(2, 4),
            default => mt_rand(1, 10) <= 9 ? 5 : 4,
        };

        if ($stage === 1) {
            return;
        }

        Carbon::setTestNow(Carbon::now()->addHours(2));

        foreach ($order->orderExams()->get() as $orderExam) {
            app(SaveOrderExamResultDraft::class)->handle($orderExam, $this->resultsFor($orderExam, complete: $stage > 2), $administrator);
        }

        if ($stage <= 3) {
            return;
        }

        Carbon::setTestNow(Carbon::now()->addHours(3));
        app(ValidateLabOrder::class)->handle($order->fresh(), $validator);

        if ($stage === 5) {
            Carbon::setTestNow(Carbon::now()->addMinutes(20));
            app(PublishOrderReport::class)->handle($order->fresh(), $validator);
        }
    }

    /**
     * @return array{0: list<int>, 1: array<int, list<string>>}
     */
    private function examsFor(Patient $patient, Collection $exams): array
    {
        $age = $patient->birth_date?->age ?? 30;
        $profiles = collect(self::PROFILES)->keys()
            ->reject(fn (string $profile): bool => ($profile === 'prenatal' && ($patient->sex !== 'femenino' || $age < 18 || $age > 42))
                || ($profile === 'prostata' && ($patient->sex !== 'masculino' || $age < 45)))
            ->values();
        $chosen = $profiles->random(mt_rand(1, 10) <= 6 ? 1 : 2);
        $selection = collect($chosen)->flatMap(fn (string $profile): array => self::PROFILES[$profile])->all();

        $examIds = [];
        $requested = [];

        foreach ($selection as $code => $parameters) {
            $exam = $exams->get($code);

            if (! $exam) {
                continue;
            }

            $examIds[] = $exam->id;

            if ($parameters !== null) {
                $requested[$exam->id] = $parameters;
            }
        }

        return [array_values(array_unique($examIds)), $requested];
    }

    /**
     * @return array{values: array<string, mixed>, section_statuses: array<string, string>}
     */
    private function resultsFor(OrderExam $orderExam, bool $complete): array
    {
        $values = [];
        $statuses = [];

        foreach ($orderExam->requestedCatalogSnapshot()['sections'] as $section) {
            if (($section['structure'] ?? null) === 'parasitological_matrix') {
                $withFindings = mt_rand(1, 100) <= 25;
                $columns = array_slice($section['settings']['columns'] ?? [], 1);

                foreach ($section['parameters'] as $position => $parameter) {
                    $values[$parameter['code']] = ['cells' => collect($columns)->mapWithKeys(fn (string $column, int $columnIndex): array => [
                        Str::slug($column, '_') => $withFindings && $position === 1 && $columnIndex === 0 ? '++' : null,
                    ])->all()];
                }

                $statuses[$section['code']] = $complete
                    ? ($withFindings ? OrderExam::SECTION_STATUS_COMPLETED_WITH_FINDINGS : OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS)
                    : OrderExam::SECTION_STATUS_PENDING;

                continue;
            }

            foreach ($section['parameters'] as $parameter) {
                if (! $complete && mt_rand(1, 2) === 1) {
                    continue;
                }

                $values[$parameter['code']] = ['value' => $this->valueFor($parameter)];
            }
        }

        return ['values' => $values, 'section_statuses' => $statuses];
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    private function valueFor(array $parameter): ?string
    {
        $code = $parameter['code'];

        return match ($parameter['result_type']) {
            'select', 'positive_negative' => mt_rand(1, 100) <= 88 ? $parameter['options'][0] : ($parameter['options'][1] ?? $parameter['options'][0]),
            'number' => $this->numberFor($parameter),
            default => isset(self::TEXT_VALUES[$code])
                ? $this->faker->randomElement(self::TEXT_VALUES[$code])
                : ($parameter['result_type'] === 'observation' ? 'Sin observaciones' : 'No se observa'),
        };
    }

    /**
     * Un número dentro del rango de referencia, salvo ~15 % fuera; los calculados se dejan vacíos para que el sistema los calcule.
     *
     * @param  array<string, mixed>  $parameter
     */
    private function numberFor(array $parameter): ?string
    {
        if (in_array($parameter['code'], ['bilirrubina_indirecta', 'colesterol_ldl', 'colesterol_vldl', 'psa_relacion_libre_total'], true)) {
            return null;
        }

        $config = $parameter['reference_config'] ?? [];
        $min = (float) ($config['min'] ?? $config['min_femenino'] ?? $config['min_masculino'] ?? 1);
        $max = (float) ($config['max'] ?? $config['max_femenino'] ?? $config['max_masculino'] ?? max($min * 3, 10));
        $span = max($max - $min, 0.1);
        $value = mt_rand(1, 100) <= 15
            ? $max + $span * mt_rand(5, 40) / 100
            : $min + $span * mt_rand(5, 95) / 100;
        $decimals = $max < 10 ? 2 : ($max < 100 ? 1 : 0);

        return number_format($value, $decimals, '.', '');
    }

    /**
     * @return Collection<int, Patient>
     */
    private function patients(): Collection
    {
        return collect(range(1, self::PATIENTS))->map(function (int $number): Patient {
            $sex = $this->faker->randomElement(['femenino', 'masculino']);

            return Patient::create([
                'first_names' => $this->faker->firstName($sex === 'femenino' ? 'female' : 'male').' '.$this->faker->firstName($sex === 'femenino' ? 'female' : 'male'),
                'last_names' => $this->faker->lastName().' '.$this->faker->lastName(),
                'birth_date' => Carbon::now()->subYears(mt_rand(2, 82))->subDays(mt_rand(0, 364))->toDateString(),
                'sex' => $sex,
                'phone' => (string) mt_rand(30000000, 59999999),
                'identifier' => sprintf('DEMO-%04d', $number),
            ]);
        });
    }

    /**
     * Visitas de los últimos 12 meses: la mayoría viene 1 o 2 veces, y algunos (controles crónicos) hasta 6.
     *
     * @param  Collection<int, Patient>  $patients
     * @return Collection<int, array{0: Patient, 1: Carbon}>
     */
    private function visits(Collection $patients): Collection
    {
        $visitsPerPatient = $patients->map(fn (Patient $patient, int $index): int => match (true) {
            $index < 6 => 5,
            $index < 15 => 3,
            $index < 30 => 2,
            default => 1,
        })->all();

        while (array_sum($visitsPerPatient) !== self::ORDERS) {
            $index = mt_rand(0, 29);
            $visitsPerPatient[$index] += array_sum($visitsPerPatient) < self::ORDERS ? 1 : ($visitsPerPatient[$index] > 1 ? -1 : 0);
        }

        $now = Carbon::now();
        $recentPatients = collect(range(30, self::PATIENTS - 1))->random(10)->flip();

        return $patients
            ->flatMap(function (Patient $patient, int $index) use ($visitsPerPatient, $recentPatients, $now): array {
                $count = $visitsPerPatient[$index];
                $slotDays = 362 / $count;

                return collect(range(1, $count))->map(function (int $visit) use ($patient, $index, $count, $slotDays, $recentPatients, $now): array {
                    $date = $recentPatients->has($index) && $visit === $count
                        ? $now->copy()->subMinutes(mt_rand(60, 72 * 60))
                        : $now->copy()->subDays(365)->addDays((int) (($visit - 1) * $slotDays) + mt_rand(0, max(1, (int) $slotDays - 1)))
                            ->setTime(mt_rand(7, 17), mt_rand(0, 59));

                    return [$patient, $date->min($now->copy()->subHour())];
                })->all();
            })
            ->sortBy(fn (array $visit): int => $visit[1]->getTimestamp())
            ->values();
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function demoUsers(): array
    {
        $users = collect(['Administrador' => 'Recepción Demo', 'Validador' => 'Química Bióloga Demo'])
            ->map(function (string $name, string $role): User {
                $user = User::firstOrCreate(
                    ['email' => Str::slug($name, '.').'@demo.invalid'],
                    ['name' => $name, 'password' => Str::random(40), 'is_active' => true],
                );
                $user->syncRoles([$role]);

                return $user;
            });

        return [$users['Administrador'], $users['Validador']];
    }
}
