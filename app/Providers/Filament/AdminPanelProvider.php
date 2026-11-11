<?php

namespace App\Providers\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Ismalej Prado')
            ->brandLogo(fn (): HtmlString => new HtmlString(
                '<span style="display:flex;align-items:center;gap:.6rem">'
                .'<img src="'.e(route('lab.brand-mark')).'" alt="" style="height:2.4rem;width:auto">'
                .'<span style="line-height:1.1;font-weight:700;color:#1d3b64">Ismalej Prado'
                .'<span style="display:block;font-size:.7rem;font-weight:500;color:#64748b">Laboratorio clínico-biológico</span></span>'
                .'</span>'
            ))
            ->brandLogoHeight('2.6rem')
            ->favicon(fn (): string => route('lab.brand-mark'))
            ->databaseTransactions()
            ->strictAuthorization()
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationLabel('Roles y permisos')
                    ->navigationGroup('Administración')
                    ->navigationSort(2),
            ])
            ->colors([
                // Azul del reporte (#1d3b64) en el tono de botones y enlaces (600).
                'primary' => [
                    50 => '#eef3f9',
                    100 => '#d9e3f1',
                    200 => '#b6c9e3',
                    300 => '#89a7cf',
                    400 => '#5b80b6',
                    500 => '#3b5f97',
                    600 => '#1d3b64',
                    700 => '#172f50',
                    800 => '#12253f',
                    900 => '#0e1c31',
                    950 => '#08111f',
                ],
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'gray' => Color::Slate,
            ])
            ->navigationGroups(['Operaciones', 'Catálogo clínico', 'Administración'])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
