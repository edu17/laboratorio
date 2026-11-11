<?php

namespace App\Filament\Resources\Patients;

use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Filament\Resources\Patients\Pages\ViewPatient;
use App\Filament\Resources\Patients\RelationManagers\OrdersRelationManager;
use App\Models\Patient;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;
use UnitEnum;

class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 2;

    protected static UnitEnum|string|null $navigationGroup = 'Operaciones';

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $modelLabel = 'paciente';

    protected static ?string $pluralModelLabel = 'Pacientes';

    protected static ?string $slug = 'pacientes';

    public static function form(Schema $schema): Schema
    {
        return $schema->components(self::formFields());
    }

    /**
     * Campos del paciente, compartidos con el alta rápida desde la orden: mismos datos obligatorios
     * y mismo aviso de duplicados en los dos lugares.
     *
     * @return list<Component>
     */
    public static function formFields(): array
    {
        return [
            TextInput::make('first_names')
                ->label('Nombres')
                ->required()
                ->maxLength(150)
                ->live(onBlur: true)
                ->rules([fn (Get $get, ?Patient $record): Closure => self::exactDuplicateRule($get, $record)]),
            TextInput::make('last_names')->label('Apellidos')->required()->maxLength(150)->live(onBlur: true),
            DatePicker::make('birth_date')
                ->label('Fecha de nacimiento')
                ->required()
                ->maxDate(today())
                ->rules(['before_or_equal:today'])
                ->helperText('Se usa para la edad del reporte.')
                ->live(onBlur: true),
            Select::make('sex')
                ->label('Sexo')
                ->options(['femenino' => 'Femenino', 'masculino' => 'Masculino'])
                ->in(['femenino', 'masculino'])
                ->required()
                ->helperText('Define qué valores de referencia se imprimen.'),
            TextInput::make('identifier')
                ->label('Documento (DPI u otro)')
                ->maxLength(100)
                ->unique(ignoreRecord: true)
                ->live(onBlur: true),
            TextInput::make('phone')->label('Teléfono')->tel()->maxLength(30),
            Placeholder::make('possible_duplicates')
                ->hiddenLabel()
                ->columnSpanFull()
                ->visible(fn (Get $get, ?Patient $record): bool => self::duplicatesFor($get, $record)->isNotEmpty())
                ->content(fn (Get $get, ?Patient $record): HtmlString => self::duplicateWarning($get, $record)),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Datos del paciente')
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('full_name')->label('Nombre completo')->weight('bold')->size('lg')->columnSpan(['sm' => 2, 'lg' => 3]),
                    TextEntry::make('identifier')->label('Documento')->placeholder('Sin registrar')->copyable(),
                    TextEntry::make('age_display')
                        ->label('Edad')
                        ->placeholder('Sin fecha de nacimiento')
                        ->helperText(fn (Patient $record): ?string => $record->birth_date?->format('d/m/Y')),
                    TextEntry::make('sex')
                        ->label('Sexo')
                        ->formatStateUsing(fn (?string $state): string => match ($state) {
                            'femenino' => 'Femenino',
                            'masculino' => 'Masculino',
                            default => 'Sin registrar',
                        })
                        ->placeholder('Sin registrar'),
                    TextEntry::make('phone')->label('Teléfono')->placeholder('Sin registrar'),
                    TextEntry::make('orders_count')->label('Órdenes')->state(fn (Patient $record): int => $record->orders()->count()),
                    TextEntry::make('latestOrder.ordered_at')->label('Última visita')->dateTime('d/m/Y')->placeholder('Sin visitas'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('orders')->withMax('orders', 'ordered_at'))
            ->defaultSort('id', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->searchPlaceholder('Buscar por nombre, apellido, documento o teléfono...')
            ->emptyStateHeading('No se encontraron pacientes')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Paciente')
                    ->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->matchingSearch($search))
                    ->sortable(['last_names', 'first_names'])
                    ->description(fn (Patient $record): ?string => $record->identifier),
                TextColumn::make('age_display')->label('Edad')->placeholder('—'),
                TextColumn::make('sex')
                    ->label('Sexo')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'femenino' => 'F',
                        'masculino' => 'M',
                        default => '—',
                    })
                    ->placeholder('—'),
                TextColumn::make('phone')->label('Teléfono')->placeholder('—')->toggleable(),
                TextColumn::make('orders_count')->label('Órdenes')->sortable()->alignCenter(),
                TextColumn::make('orders_max_ordered_at')->label('Última visita')->date('d/m/Y')->sortable()->placeholder('—'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->recordUrl(fn (Patient $record): string => self::getUrl('view', ['record' => $record]))
            ->toolbarActions([]);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPatients::route('/'),
            'create' => CreatePatient::route('/create'),
            'view' => ViewPatient::route('/{record}'),
            'edit' => EditPatient::route('/{record}/edit'),
        ];
    }

    /**
     * @return Collection<int, Patient>
     */
    private static function duplicatesFor(Get $get, ?Patient $record): Collection
    {
        if (blank($get('last_names')) && blank($get('identifier'))) {
            return new Collection;
        }

        return Patient::possibleDuplicates($get('first_names'), $get('last_names'), $get('birth_date'), $get('identifier'), $record?->getKey());
    }

    private static function duplicateWarning(Get $get, ?Patient $record): HtmlString
    {
        $items = self::duplicatesFor($get, $record)->map(fn (Patient $patient): string => sprintf(
            '<li><a href="%s" target="_blank" style="text-decoration:underline">%s</a> · %s · %s</li>',
            e(self::getUrl('view', ['record' => $patient])),
            e($patient->full_name),
            e($patient->identifier ?? 'sin documento'),
            e($patient->age_display ?? 'sin fecha de nacimiento'),
        ))->implode('');

        return new HtmlString(
            '<div style="padding:.75rem 1rem;border-radius:.5rem;background:#fffbeb;border:1px solid #f59e0b;color:#78350f">'
            .'<strong>Posible paciente ya registrado.</strong> Revisa antes de crear uno nuevo; si es la misma persona, usa su registro para que todos sus exámenes queden juntos.'
            .'<ul style="margin:.4rem 0 0 1.2rem;list-style:disc">'.$items.'</ul></div>'
        );
    }

    /**
     * Bloquea un duplicado exacto: mismo nombre completo y misma fecha de nacimiento.
     */
    private static function exactDuplicateRule(Get $get, ?Patient $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
            if (blank($get('birth_date'))) {
                return;
            }

            $fullName = Patient::comparableName($value.' '.$get('last_names'));
            $exists = Patient::possibleDuplicates($value, $get('last_names'), $get('birth_date'), null, $record?->getKey())
                ->contains(fn (Patient $patient): bool => Patient::comparableName($patient->full_name) === $fullName
                    && $patient->birth_date?->isSameDay($get('birth_date')));

            if ($exists) {
                $fail('Ya existe un paciente con el mismo nombre y fecha de nacimiento. Usa ese registro.');
            }
        };
    }
}
