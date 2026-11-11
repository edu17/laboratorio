<?php

namespace App\Filament\Resources\ExamParameters\Schemas;

use App\Actions\Results\ResolveParameterReference;
use App\Models\ExamParameter;
use App\Models\ExamSection;
use Closure;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

class ExamParameterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('exam_section_id')
                    ->label('Examen y sección')
                    ->relationship('section', 'name', modifyQueryUsing: fn (Builder $query): Builder => $query->with('exam')->orderBy('exam_id')->orderBy('sort_order'))
                    ->getOptionLabelFromRecordUsing(fn (ExamSection $record): string => self::sectionLabel($record))
                    ->getSearchResultsUsing(fn (string $search): array => ExamSection::query()
                        ->with('exam')
                        ->where(fn (Builder $query): Builder => $query
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhereHas('exam', fn (Builder $exam): Builder => $exam
                                ->where('code', 'like', '%'.$search.'%')
                                ->orWhere('name', 'like', '%'.$search.'%')))
                        ->orderBy('exam_id')
                        ->orderBy('sort_order')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (ExamSection $section): array => [$section->getKey() => self::sectionLabel($section)])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->live()
                    ->helperText('Busca por examen o sección. El código del examen aparece antes del nombre de la sección.')
                    ->required(),
                TextInput::make('code')->label('Identificador')->required()->alphaDash()->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('exam_section_id', $get('exam_section_id'))),
                TextInput::make('name')->label('Nombre del parámetro')->required()->maxLength(180),
                Select::make('result_type')->label('Tipo de resultado')->options([
                    'text' => 'Texto',
                    'number' => 'Número',
                    'select' => 'Selección',
                    'positive_negative' => 'Positivo / negativo',
                    'observation' => 'Observación',
                    'matrix_row' => 'Fila de matriz',
                ])->required()->live(),
                TextInput::make('unit')->label('Unidad')->maxLength(60)->placeholder('Pendiente si no está confirmada'),
                Textarea::make('reference_text')->label('Referencia')->maxLength(2000)->rows(3)
                    ->placeholder('Pendiente si no está confirmada')->columnSpanFull(),
                KeyValue::make('reference_config')->label('Configuración estructurada de referencia')
                    ->keyLabel('Clave')->valueLabel('Valor')
                    ->helperText('Claves: min, max (rango para marcar fuera de rango), min_femenino, max_femenino, min_masculino, max_masculino, texto_femenino, texto_masculino (referencia impresa según el sexo del paciente).')
                    ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        foreach ((array) $value as $key => $configValue) {
                            if (! in_array($key, ResolveParameterReference::ALLOWED_CONFIG_KEYS, true)) {
                                $fail("La clave «{$key}» no está permitida.");
                            } elseif (preg_match('/\A(min|max)(_|\z)/', (string) $key) === 1 && ! is_numeric($configValue)) {
                                $fail("El valor de «{$key}» debe ser un número.");
                            }
                        }
                    }])
                    ->columnSpanFull(),
                TagsInput::make('options')->label('Opciones permitidas')
                    ->required(fn (Get $get): bool => in_array($get('result_type'), ['select', 'positive_negative'], true))
                    ->visible(fn (Get $get): bool => in_array($get('result_type'), ['select', 'positive_negative'], true))
                    ->columnSpanFull(),
                TextInput::make('source')->label('Fuente de estructura')->maxLength(180),
                Placeholder::make('clinical_validation_status')
                    ->label('Estado de validación clínica')
                    ->content(fn (?ExamParameter $record): string => $record?->clinical_validation_status === 'validated'
                        ? 'Validado clínicamente'
                        : 'Pendiente de validación clínica'),
                Toggle::make('is_active')->label('Activo')->default(true),
                TextInput::make('sort_order')->label('Orden')->numeric()->minValue(0)->default(0)->required(),
            ]);
    }

    private static function sectionLabel(ExamSection $section): string
    {
        return $section->exam->code.' — '.$section->name;
    }
}
