<?php

namespace App\Filament\Pages;

use App\Actions\Settings\UpdateLabProfile;
use App\Models\LabProfile;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class LabSettings extends Page
{
    protected string $view = 'filament.pages.lab-settings';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static UnitEnum|string|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Laboratorio';

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('Manage:LabSettings') ?? false;
    }

    public function mount(): void
    {
        Gate::authorize('Manage:LabSettings');

        $this->form->fill(LabProfile::query()->where('is_active', true)->firstOrFail()->only([
            'institution_name',
            'subtitle',
            'address',
            'phone',
            'email',
            'sanitary_registration',
            'emergency_phone',
            'opening_hours',
            'logo_path',
            'technical_signature_path',
            'biologist_signature_path',
            'institutional_seal_path',
            'order_prefix',
            'order_number_padding',
        ]));
    }

    public function hydrate(): void
    {
        Gate::authorize('Manage:LabSettings');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos institucionales')
                    ->description('Estos datos se guardan como una versión. Los reportes ya validados conservan la versión que tenían al validarse.')
                    ->schema([
                        TextInput::make('institution_name')->label('Nombre del laboratorio')->required()->maxLength(180)->columnSpanFull(),
                        TextInput::make('subtitle')->label('Texto secundario del reporte')->maxLength(255)->columnSpanFull(),
                        Textarea::make('address')->label('Dirección')->rows(2)->maxLength(1000)->columnSpanFull(),
                        TextInput::make('phone')->label('Teléfono')->tel()->maxLength(40),
                        TextInput::make('email')->label('Correo electrónico')->email()->maxLength(180),
                        TextInput::make('sanitary_registration')->label('R. Sanitario -DRACES-')->maxLength(100),
                        TextInput::make('emergency_phone')->label('Teléfono de emergencias')->tel()->maxLength(40),
                        TextInput::make('opening_hours')->label('Horario de atención')->maxLength(255)->columnSpanFull(),
                        FileUpload::make('logo_path')
                            ->label('Logotipo principal')
                            ->disk('local')
                            ->directory('lab-profile-logos')
                            ->visibility('private')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png'])
                            ->maxSize(2048)
                            ->helperText('PNG o JPEG, máximo 2 MB. Se almacena de forma privada y solo se usa en el PDF.'),
                        FileUpload::make('technical_signature_path')
                            ->label('Firma del técnico laboratorista')
                            ->disk('local')
                            ->directory('lab-report-assets')
                            ->visibility('private')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png'])
                            ->maxSize(2048)
                            ->helperText('PNG o JPEG, máximo 2 MB. Solo aparecerá en el pie del PDF.'),
                        FileUpload::make('biologist_signature_path')
                            ->label('Firma de la química bióloga')
                            ->disk('local')
                            ->directory('lab-report-assets')
                            ->visibility('private')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png'])
                            ->maxSize(2048)
                            ->helperText('PNG o JPEG, máximo 2 MB. Solo aparecerá en el pie del PDF.'),
                        FileUpload::make('institutional_seal_path')
                            ->label('Sello institucional')
                            ->disk('local')
                            ->directory('lab-report-assets')
                            ->visibility('private')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png'])
                            ->maxSize(2048)
                            ->helperText('PNG o JPEG, máximo 2 MB. Solo aparecerá en el pie del PDF.'),
                    ])
                    ->columns(2),
                Section::make('Correlativo de órdenes')
                    ->description('Los cambios afectan únicamente a las órdenes nuevas. Los números anteriores no se modifican.')
                    ->schema([
                        TextInput::make('order_prefix')
                            ->label('Prefijo')
                            ->required()
                            ->maxLength(12)
                            ->regex('/\A[A-Z][A-Z0-9-]{0,11}\z/i')
                            ->helperText('Letras, números y guiones. Ejemplo actual: LAB.'),
                        TextInput::make('order_number_padding')
                            ->label('Dígitos mínimos de secuencia')
                            ->numeric()
                            ->integer()
                            ->minValue(3)
                            ->maxValue(10)
                            ->required()
                            ->helperText('El contador continúa; este valor solo controla los ceros a la izquierda.'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(UpdateLabProfile $updateLabProfile): void
    {
        $actor = auth()->user();

        abort_unless($actor instanceof User, 403);

        $profile = $updateLabProfile->handle($this->form->getState(), $actor);
        $this->form->fill($profile->only([
            'institution_name',
            'subtitle',
            'address',
            'phone',
            'email',
            'sanitary_registration',
            'emergency_phone',
            'opening_hours',
            'logo_path',
            'technical_signature_path',
            'biologist_signature_path',
            'institutional_seal_path',
            'order_prefix',
            'order_number_padding',
        ]));

        Notification::make()
            ->success()
            ->title('Configuración guardada')
            ->body('Se creó una nueva versión del perfil del laboratorio.')
            ->send();
    }
}
