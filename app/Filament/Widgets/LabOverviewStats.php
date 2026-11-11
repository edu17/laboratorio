<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Collection;

class LabOverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Order::class) ?? false;
    }

    protected function getStats(): array
    {
        $todayOrders = Order::query()
            ->whereDate('ordered_at', today())
            ->where('status', '!=', Order::STATUS_CANCELED)
            ->withCount('orderExams')
            ->get();
        $openProgress = self::openOrders()->map(fn (Order $order): array => $order->resultProgress());
        $toCapture = $openProgress->filter(fn (array $progress): bool => $progress['missing'] > 0);
        $readyToValidate = $openProgress->filter(fn (array $progress): bool => $progress['missing'] === 0 && $progress['required'] > 0)->count();
        $validated = Order::query()->where('status', Order::STATUS_VALIDATED)->count();
        $pendingResults = $toCapture->sum('missing');

        return [
            Stat::make('Órdenes de hoy', $todayOrders->count())
                ->description($todayOrders->sum('order_exams_count').' exámenes solicitados')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary')
                ->url(OrderResource::getUrl('index')),
            Stat::make('Por capturar', $toCapture->count())
                ->description($pendingResults === 1 ? '1 resultado pendiente' : $pendingResults.' resultados pendientes')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color($toCapture->isEmpty() ? 'gray' : 'warning'),
            Stat::make('Listas para validar', $readyToValidate)
                ->description('Todos los resultados capturados')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($readyToValidate === 0 ? 'gray' : 'info'),
            Stat::make('Validadas sin publicar', $validated)
                ->description('Reporte listo para entregar')
                ->descriptionIcon('heroicon-m-document-text')
                ->color($validated === 0 ? 'gray' : 'success')
                ->url(OrderResource::getUrl('index', ['filters' => ['status' => ['value' => Order::STATUS_VALIDATED]]])),
        ];
    }

    /**
     * Órdenes abiertas con lo necesario para calcular su avance en una sola carga.
     *
     * @return Collection<int, Order>
     */
    private static function openOrders(): Collection
    {
        return Order::query()
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_IN_PROGRESS])
            ->with('orderExams.resultValues')
            ->get();
    }
}
