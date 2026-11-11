<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Results\CalculateDerivedResults;
use App\Actions\Results\ResolveParameterReference;
use App\Actions\Results\SaveOrderExamResultDraft;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\ResultValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class CaptureOrderExamResults extends Page
{
    protected static string $resource = OrderResource::class;

    protected string $view = 'filament.resources.orders.pages.capture-order-exam-results';

    protected static bool $shouldRegisterNavigation = false;

    #[Locked]
    public int $orderId;

    #[Locked]
    public int $orderExamId;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(int|string $record, int|string $orderExam): void
    {
        $order = Order::query()->findOrFail($record);
        Gate::authorize('view', $order);

        $this->orderId = (int) $order->getKey();
        $this->orderExamId = (int) $orderExam;

        $orderExamRecord = $this->getOrderExam();
        Gate::authorize('captureResults', $orderExamRecord);

        $this->form->fill($this->getFormValues($orderExamRecord));
    }

    public function hydrate(): void
    {
        Gate::authorize('view', Order::query()->findOrFail($this->orderId));
        Gate::authorize('captureResults', $this->getOrderExam());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->getSectionComponents())
            ->statePath('data');
    }

    public function save(SaveOrderExamResultDraft $saveDraft): void
    {
        $state = $this->form->getState();

        $savedOrderExam = $saveDraft->handle($this->getOrderExam(), $state, auth()->user());
        $this->form->fill($this->getFormValues($savedOrderExam));

        Notification::make()
            ->success()
            ->title('Borrador de resultados guardado')
            ->body('El examen sigue pendiente de revisión y validación.')
            ->send();
    }

    public function getOrderExam(): OrderExam
    {
        return OrderExam::query()
            ->where('order_id', $this->orderId)
            ->with(['order.patient', 'resultValues'])
            ->findOrFail($this->orderExamId);
    }

    public function getMissingResultCount(): int
    {
        $orderExam = $this->getOrderExam();
        $storedValues = $orderExam->resultValues->keyBy('parameter_code');
        $sectionStatuses = $orderExam->section_statuses ?? [];
        $missingCount = 0;

        foreach ($orderExam->requestedCatalogSnapshot()['sections'] ?? [] as $section) {
            $isMatrix = ($section['structure'] ?? null) === 'parasitological_matrix';

            if ($isMatrix) {
                $sectionCode = $section['code'] ?? null;
                $hasFindings = collect($section['parameters'] ?? [])
                    ->flatMap(fn (array $parameter): array => array_values($storedValues->get($parameter['code'] ?? '')?->value['cells'] ?? []))
                    ->contains(fn (mixed $cell): bool => filled($cell));
                $status = is_string($sectionCode) ? ($sectionStatuses[$sectionCode] ?? OrderExam::SECTION_STATUS_PENDING) : OrderExam::SECTION_STATUS_PENDING;
                $isComplete = match ($status) {
                    OrderExam::SECTION_STATUS_COMPLETED_WITH_FINDINGS => $hasFindings,
                    OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS => ! $hasFindings,
                    default => false,
                };

                if (! $isComplete) {
                    $missingCount++;
                }

                continue;
            }

            foreach ($section['parameters'] ?? [] as $parameter) {
                $code = $parameter['code'] ?? null;

                if (! is_string($code)) {
                    continue;
                }

                $stored = $storedValues->get($code)?->value ?? [];

                if (blank($stored['value'] ?? null)) {
                    $missingCount++;
                }
            }
        }

        return $missingCount;
    }

    public function getOrderViewUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->orderId]);
    }

    /**
     * @return list<Section>
     */
    private function getSectionComponents(): array
    {
        $orderExam = $this->getOrderExam();
        $patientSex = $orderExam->order?->patient?->sex;

        return collect($orderExam->requestedCatalogSnapshot()['sections'] ?? [])
            ->map(function (array $section) use ($patientSex): Section {
                $components = [];
                $isMatrix = ($section['structure'] ?? null) === 'parasitological_matrix';
                $columns = $isMatrix ? array_slice($section['settings']['columns'] ?? [], 1) : [];

                if ($isMatrix && is_string($section['code'] ?? null)) {
                    $components[] = Select::make("section_statuses.{$section['code']}")
                        ->label('Estado de la matriz')
                        ->options([
                            OrderExam::SECTION_STATUS_PENDING => 'Pendiente',
                            OrderExam::SECTION_STATUS_COMPLETED_WITH_FINDINGS => 'Completada con hallazgos',
                            OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS => 'Completada sin hallazgos',
                        ])
                        ->default(OrderExam::SECTION_STATUS_PENDING)
                        ->required()
                        ->helperText('Para indicar que no se encontró ningún organismo, deja todas las celdas vacías y selecciona “Completada sin hallazgos”.');
                }

                foreach ($section['parameters'] ?? [] as $parameter) {
                    $code = $parameter['code'] ?? null;

                    if (! is_string($code) || $code === '') {
                        continue;
                    }

                    if ($isMatrix) {
                        foreach ($columns as $column) {
                            $columnKey = Str::slug($column, '_');
                            $components[] = TextInput::make("values.{$code}.cells.{$columnKey}")
                                ->label(($parameter['name'] ?? $code).' — '.$column)
                                ->maxLength(2000);
                        }

                        continue;
                    }

                    $type = $parameter['type'] ?? $parameter['result_type'] ?? 'text';
                    $field = match ($type) {
                        'number' => TextInput::make("values.{$code}.value")
                            ->maxLength(30)
                            ->regex(CalculateDerivedResults::acceptsNotCalculable($code)
                                ? CalculateDerivedResults::NUMBER_OR_NOT_CALCULABLE_PATTERN
                                : ResolveParameterReference::NUMBER_PATTERN)
                            ->placeholder('Ej. 1.02 o < 2.5'),
                        'select', 'positive_negative' => $this->getChoiceField($code, $parameter),
                        'observation' => Textarea::make("values.{$code}.value")->rows(3),
                        default => TextInput::make("values.{$code}.value"),
                    };

                    $helperText = collect([
                        $parameter['unit'] ?? null,
                        app(ResolveParameterReference::class)->handle($parameter, $patientSex, null)['text'],
                        isset(CalculateDerivedResults::FORMULAS[$code])
                            ? 'Se calcula al guardar si lo dejas vacío: '.CalculateDerivedResults::FORMULAS[$code]['description'].'. Si no aplica queda "No calculable". Puedes corregirlo escribiendo otro valor.'
                            : null,
                    ])->filter(fn ($text): bool => filled($text))->implode(' · ');

                    $field->label($parameter['name'] ?? $code);

                    if (in_array($type, ['text', 'observation'], true)) {
                        $field->maxLength(2000);
                    }

                    if ($helperText !== '') {
                        $field->helperText($helperText);
                    }

                    $components[] = $field;
                }

                return Section::make($section['name'] ?? $section['code'] ?? 'Sección')
                    ->schema($components)
                    ->columns(($section['structure'] ?? null) === 'parasitological_matrix' ? 2 : 2);
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $parameter
     */
    private function getChoiceField(string $code, array $parameter): Select|TextInput
    {
        $options = $parameter['options'] ?? [];

        if (! is_array($options) || $options === []) {
            return TextInput::make("values.{$code}.value")
                ->disabled()
                ->helperText('Configura las opciones permitidas en el catálogo antes de capturar este resultado.');
        }

        return Select::make("values.{$code}.value")
            ->options(collect($options)->filter(fn ($option): bool => is_string($option))->mapWithKeys(fn (string $option): array => [$option => $option])->all())
            ->searchable();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getFormValues(OrderExam $orderExam): array
    {
        $sectionStatuses = $orderExam->section_statuses ?? [];

        foreach ($orderExam->requestedCatalogSnapshot()['sections'] ?? [] as $section) {
            if (($section['structure'] ?? null) !== 'parasitological_matrix' || ! is_string($section['code'] ?? null)) {
                continue;
            }

            $sectionStatuses[$section['code']] ??= OrderExam::SECTION_STATUS_PENDING;
        }

        return [
            'values' => $orderExam->resultValues
                ->mapWithKeys(fn (ResultValue $result): array => [$result->parameter_code => $result->value ?? []])
                ->all(),
            'section_statuses' => $sectionStatuses,
        ];
    }
}
