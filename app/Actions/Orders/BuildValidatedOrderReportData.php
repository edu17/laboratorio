<?php

namespace App\Actions\Orders;

use App\Actions\Results\ResolveParameterReference;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\OrderValidationEvent;
use App\Models\ReportAccessToken;
use App\Models\User;
use chillerlan\QRCode\QRCode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BuildValidatedOrderReportData
{
    public function __construct(private ResolveParameterReference $resolveReference) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Order $order, User $actor): array
    {
        $order = Order::query()->findOrFail($order->getKey());
        Gate::forUser($actor)->authorize('generateReport', $order);

        return $this->build($order);
    }

    /**
     * @return array<string, mixed>
     */
    public function handlePublicAccess(ReportAccessToken $accessToken): array
    {
        $accessToken->load('order');
        $order = $accessToken->order;

        abort_unless(
            $accessToken->revoked_at === null
                && ($accessToken->expires_at === null || $accessToken->expires_at->isFuture())
                && $accessToken->validation_version === $order->validation_version
                && $order->status === Order::STATUS_PUBLISHED,
            404,
        );

        return $this->build($order);
    }

    /** @return array<string, mixed> */
    private function build(Order $order): array
    {
        $event = $order->validationEvents()
            ->where('version', $order->validation_version)
            ->where('action', OrderValidationEvent::ACTION_VALIDATED)
            ->first();

        if (! $event || ! is_array($event->results_snapshot)) {
            throw new NotFoundHttpException('No se encontro la version validada del reporte.');
        }

        $snapshot = $event->results_snapshot;

        if (! isset($snapshot['order'], $snapshot['patient'], $snapshot['validated_by'], $snapshot['exams'])) {
            throw new NotFoundHttpException('La version validada no contiene el contexto completo del reporte.');
        }

        $report = [
            'template_version' => '1',
            'generated_at' => now()->format('d/m/Y H:i'),
            'institution' => $this->formatInstitution($snapshot['lab_profile'] ?? []),
            'order' => [
                ...$snapshot['order'],
                'ordered_at_display' => Carbon::parse($snapshot['order']['ordered_at'])->format('d/m/Y H:i'),
            ],
            'patient' => [
                ...$snapshot['patient'],
                'age_display' => $this->ageDisplay($snapshot['patient']['birth_date'] ?? null, $snapshot['validated_at'] ?? $event->acted_at),
                'gender_display' => match ($snapshot['patient']['sex'] ?? null) {
                    'femenino' => 'Femenino',
                    'masculino' => 'Masculino',
                    default => 'Sin registrar',
                },
            ],
            'validation' => [
                'version' => $event->version,
                'validated_at_display' => Carbon::parse($snapshot['validated_at'] ?? $event->acted_at)->format('d/m/Y H:i'),
                'validator_name' => $snapshot['validated_by']['name'] ?? $event->actor->name,
            ],
            'exams' => array_map(fn (array $exam): array => $this->formatExam($exam, $snapshot['patient']['sex'] ?? null), $snapshot['exams']),
        ];

        $activeAccessToken = $order->reportAccessTokens()
            ->whereNull('revoked_at')
            ->where('validation_version', $event->version)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();

        if ($order->status === Order::STATUS_PUBLISHED && $activeAccessToken) {
            $publicUrl = route('reports.public', ['token' => $activeAccessToken->token_encrypted]);
            $report['public_access'] = [
                'qr_code' => (new QRCode)->render($publicUrl),
            ];
        }

        return $report;
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, string|null>
     */
    private function formatInstitution(array $profile): array
    {
        return [
            'name' => $profile['institution_name'] ?? 'LABORATORIO CLÍNICO-BIOLÓGICO ISMALEJ PRADO',
            'subtitle' => $profile['subtitle'] ?? 'Reporte de resultados de laboratorio',
            'address' => $profile['address'] ?? null,
            'phone' => $profile['phone'] ?? null,
            'email' => $profile['email'] ?? null,
            'sanitary_registration' => $profile['sanitary_registration'] ?? null,
            'emergency_phone' => $profile['emergency_phone'] ?? null,
            'opening_hours' => $profile['opening_hours'] ?? null,
            'logo_data_uri' => $this->privateImageDataUri($profile['logo_path'] ?? null, 'lab-profile-logos'),
            'technical_signature_data_uri' => $this->privateImageDataUri(
                $profile['technical_signature_path'] ?? null,
                'lab-report-assets',
            ),
            'biologist_signature_data_uri' => $this->privateImageDataUri(
                $profile['biologist_signature_path'] ?? null,
                'lab-report-assets',
            ),
            'institutional_seal_data_uri' => $this->privateImageDataUri(
                $profile['institutional_seal_path'] ?? null,
                'lab-report-assets',
            ),
        ];
    }

    private function ageDisplay(?string $birthDate, mixed $reportDate): string
    {
        if (! $birthDate) {
            return 'Sin registrar';
        }

        $birth = Carbon::parse($birthDate)->startOfDay();
        $date = Carbon::parse($reportDate)->startOfDay();

        if ($birth->greaterThan($date)) {
            return 'Sin registrar';
        }

        $years = (int) $birth->diffInYears($date);
        $months = (int) $birth->diffInMonths($date);
        $days = (int) $birth->diffInDays($date);

        return match (true) {
            $years > 0 => $years.($years === 1 ? ' año' : ' años'),
            $months > 0 => $months.($months === 1 ? ' mes' : ' meses'),
            default => $days.($days === 1 ? ' día' : ' días'),
        };
    }

    private function privateImageDataUri(mixed $path, string $directory): ?string
    {
        if (
            ! is_string($path)
            || ! in_array($directory, ['lab-profile-logos', 'lab-report-assets'], true)
            || preg_match('/\A'.preg_quote($directory, '/').'\/[A-Za-z0-9._-]+\.(?:png|jpe?g)\z/i', $path) !== 1
        ) {
            return null;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($path) || $disk->size($path) > 2 * 1024 * 1024) {
            return null;
        }

        $mimeType = $disk->mimeType($path);

        if (! in_array($mimeType, ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($disk->get($path));
    }

    /**
     * @param  array<string, mixed>  $exam
     * @return array<string, mixed>
     */
    private function formatExam(array $exam, ?string $patientSex): array
    {
        $catalog = $exam['catalog_snapshot'] ?? [];
        $storedResults = collect($exam['results'] ?? [])->keyBy('parameter_code');
        $sections = [];

        foreach ($catalog['sections'] ?? [] as $section) {
            $matrix = ($section['structure'] ?? null) === 'parasitological_matrix';
            $columns = $matrix ? array_values($section['settings']['columns'] ?? []) : [];
            $sectionCode = $section['code'] ?? null;
            $status = $matrix && is_string($sectionCode)
                ? ($exam['section_statuses'][$sectionCode] ?? OrderExam::SECTION_STATUS_PENDING)
                : null;
            $rows = [];

            foreach ($matrix && $status === OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS
                ? []
                : ($section['parameters'] ?? []) as $parameter) {
                $code = $parameter['code'] ?? null;

                if (! is_string($code) || $code === '') {
                    continue;
                }

                $stored = $storedResults->get($code)['value'] ?? [];
                $stored = is_array($stored) ? $stored : [];

                if ($matrix) {
                    $cells = $stored['cells'] ?? [];
                    $rows[] = [
                        'name' => $parameter['name'] ?? $code,
                        'cells' => array_map(
                            fn (string $column): string => $this->displayValue($cells[Str::slug($column, '_')] ?? null),
                            array_slice($columns, 1),
                        ),
                    ];

                    continue;
                }

                $referenceApproved = ($parameter['clinical_validation_status'] ?? null) === 'validated';
                $reference = $this->resolveReference->handle($parameter, $patientSex, $stored['value'] ?? null);
                $rows[] = [
                    'name' => $parameter['name'] ?? $code,
                    'result' => $this->displayValue($stored['value'] ?? null),
                    'unit' => $this->displayValue($parameter['unit'] ?? null),
                    'reference' => $referenceApproved ? $reference['text'] : '',
                    'flag' => $referenceApproved ? $reference['flag'] : null,
                ];
            }

            if ($rows !== [] || ($matrix && $status === OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS)) {
                $sections[] = [
                    'name' => $section['name'] ?? $section['code'] ?? 'Seccion',
                    'layout' => match ($section['settings']['report_layout'] ?? null) {
                        'paired' => 'paired',
                        'standard' => 'standard',
                        default => ($catalog['exam']['code'] ?? '') === 'HECES' ? 'paired' : 'standard',
                    },
                    'is_matrix' => $matrix,
                    'status' => $status,
                    'columns' => $columns,
                    'rows' => $rows,
                ];
            }
        }

        return [
            'code' => $catalog['exam']['code'] ?? '',
            'name' => $catalog['exam']['name'] ?? 'Examen',
            'sections' => $sections,
        ];
    }

    private function displayValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
