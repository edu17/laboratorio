<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Actions\Orders\BuildOrderExamSnapshot;
use App\Filament\Resources\Patients\PatientResource;
use App\Filament\Resources\ReferringDoctors\ReferringDoctorResource;
use App\Models\Exam;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\Patient;
use App\Models\ReferringDoctor;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('patient_id')
                    ->label('Paciente')
                    ->relationship('patient', 'first_names')
                    ->getOptionLabelFromRecordUsing(fn (Patient $record): string => self::patientLabel($record))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Patient::query()
                        ->matchingSearch($search)
                        ->orderBy('last_names')
                        ->limit(25)
                        ->get()
                        ->mapWithKeys(fn (Patient $patient): array => [$patient->getKey() => self::patientLabel($patient)])
                        ->all())
                    ->searchPrompt('Escribe nombre, apellido, documento o teléfono')
                    ->noSearchResultsMessage('No hay pacientes con esos datos: regístralo con el botón +')
                    ->default(fn (): ?int => request()->integer('patient') ?: null)
                    ->required()
                    ->createOptionForm(PatientResource::formFields())
                    ->createOptionAction(fn ($action) => $action->label('Registrar paciente')->modalWidth('3xl'))
                    ->createOptionUsing(function (array $data): int {
                        Gate::authorize('create', Patient::class);

                        return Patient::create($data)->getKey();
                    }),
                CheckboxList::make('exam_ids')
                    ->label('Exámenes solicitados')
                    ->options(fn (?Order $record): array => Exam::query()
                        ->where('is_active', true)
                        ->orWhereIn('id', $record?->orderExams()->pluck('exam_id')->all() ?? [])
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->default([])
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?array $state, ?array $old, Set $set, ?Order $record): void {
                        foreach (array_diff($state ?? [], $old ?? []) as $examId) {
                            $options = self::requestableParameterOptions((int) $examId, $record);

                            if ($options !== []) {
                                $set("requested_parameter_codes.{$examId}", array_keys($options));
                            }
                        }
                    })
                    ->columnSpanFull(),
                Group::make(fn (Get $get, ?Order $record): array => self::requestedParameterFields($get('exam_ids') ?? [], $record))
                    ->columnSpanFull(),
                Select::make('referring_doctor_id')
                    ->label('Médico remitente')
                    ->relationship(
                        'referringDoctor',
                        'name',
                        fn (Builder $query, ?Order $record): Builder => $query
                            ->where(fn (Builder $query): Builder => $query
                                ->where('is_active', true)
                                ->when($record?->referring_doctor_id, fn (Builder $query, int $currentId): Builder => $query->orWhere('id', $currentId)))
                            ->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (ReferringDoctor $record): string => $record->name.($record->specialty ? ' — '.$record->specialty : ''))
                    ->searchable(['name', 'specialty', 'license_number'])
                    ->preload()
                    ->placeholder('Sin referencia')
                    ->helperText('Déjalo vacío si el paciente viene sin referencia.')
                    ->createOptionForm(ReferringDoctorResource::formFields())
                    ->createOptionAction(fn ($action) => $action->label('Registrar médico remitente'))
                    ->createOptionUsing(function (array $data): int {
                        Gate::authorize('create', ReferringDoctor::class);

                        return ReferringDoctor::create($data)->getKey();
                    }),
                Textarea::make('notes')->label('Observaciones administrativas')->maxLength(5000)->columnSpanFull(),
                DatePicker::make('ordered_at')->label('Fecha de orden')->default(today())->disabled()->dehydrated(false),
                TextInput::make('status')->label('Estado')->default('pendiente')->disabled()->dehydrated(false),
            ]);
    }

    /**
     * "Nombre Apellido · 34 años · F · DPI": suficiente para distinguir a dos pacientes con el mismo nombre.
     */
    public static function patientLabel(Patient $patient): string
    {
        return collect([
            $patient->full_name,
            $patient->age_display,
            match ($patient->sex) {
                'femenino' => 'F',
                'masculino' => 'M',
                default => null,
            },
            $patient->identifier,
        ])->filter()->implode(' · ');
    }

    /**
     * Una lista de análisis por cada examen marcado que se puede pedir de forma parcial.
     *
     * @param  array<int|string>  $examIds
     * @return list<CheckboxList>
     */
    private static function requestedParameterFields(array $examIds, ?Order $record): array
    {
        $exams = Exam::query()
            ->whereIn('id', $examIds)
            ->where('allows_partial_request', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $exams
            ->mapWithKeys(fn (Exam $exam): array => [$exam->getKey() => self::requestableParameterOptions($exam->getKey(), $record)])
            ->filter(fn (array $options): bool => $options !== [])
            ->map(fn (array $options, int $examId): CheckboxList => CheckboxList::make("requested_parameter_codes.{$examId}")
                ->label('Análisis solicitados — '.$exams->firstWhere('id', $examId)->name)
                ->helperText('Desmarca los análisis que no se van a realizar. Las secciones sin análisis no aparecen en el reporte.')
                ->options($options)
                ->bulkToggleable()
                ->columns(2)
                ->required())
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function requestableParameterOptions(int $examId, ?Order $record): array
    {
        $orderExam = $record?->orderExams()->where('exam_id', $examId)->first();

        if ($orderExam) {
            return OrderExam::requestableParameters($orderExam->catalog_snapshot ?? []);
        }

        $exam = Exam::query()->with('latestCatalogVersion')->find($examId);

        if (! $exam?->allows_partial_request || ! $exam->is_active) {
            return [];
        }

        return OrderExam::requestableParameters(app(BuildOrderExamSnapshot::class)->handle($exam));
    }
}
