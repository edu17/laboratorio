<?php

namespace App\Actions\Orders;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderExam;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Libro de Excel con las órdenes de la consulta recibida (la lista con sus filtros y búsqueda), una fila por
 * orden con los datos del paciente tal como están registrados. Las fechas van como fechas reales de Excel para
 * poder ordenarlas y filtrarlas sin corregirlas a mano.
 */
class ExportOrdersSpreadsheet
{
    private const HEADERS = [
        'Orden', 'Fecha de orden', 'Estado', 'Nombres', 'Apellidos', 'Fecha de nacimiento', 'Edad', 'Sexo',
        'Documento', 'Teléfono', 'Médico remitente', 'Exámenes', 'Validada por', 'Fecha de validación',
    ];

    private const COLUMN_WIDTHS = [16, 16, 12, 22, 22, 14, 10, 11, 18, 12, 28, 50, 24, 16];

    /**
     * @param  Builder<Order>  $query
     * @return string Ruta del archivo temporal generado.
     */
    public function handle(Builder $query, User $actor): string
    {
        Gate::forUser($actor)->authorize('export', Order::class);

        $path = tempnam(sys_get_temp_dir(), 'ordenes');
        $options = new Options;

        foreach (self::COLUMN_WIDTHS as $index => $width) {
            $options->setColumnWidth($width, $index + 1);
        }

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Órdenes');
        $writer->addRow(Row::fromValues(self::HEADERS, (new Style)->setFontBold()->setBackgroundColor('D9E3F1')));

        $dateStyle = (new Style)->setFormat('dd/mm/yyyy');
        $dateTimeStyle = (new Style)->setFormat('dd/mm/yyyy hh:mm');
        $count = 0;

        $query->clone()
            ->with(['patient', 'orderExams.exam', 'validatedBy'])
            ->reorder('ordered_at')
            ->orderBy('id')
            ->chunk(500, function ($orders) use ($writer, $dateStyle, $dateTimeStyle, &$count): void {
                foreach ($orders as $order) {
                    /** @var Order $order */
                    $patient = $order->patient;

                    $writer->addRow(new Row([
                        Cell::fromValue($order->number),
                        Cell::fromValue($order->ordered_at?->toDateTimeImmutable(), $dateTimeStyle),
                        Cell::fromValue(Order::STATUS_LABELS[$order->status] ?? $order->status),
                        Cell::fromValue($patient?->first_names),
                        Cell::fromValue($patient?->last_names),
                        Cell::fromValue($patient?->birth_date?->toDateTimeImmutable(), $dateStyle),
                        Cell::fromValue($patient?->age_display),
                        Cell::fromValue(match ($patient?->sex) {
                            'femenino' => 'Femenino',
                            'masculino' => 'Masculino',
                            default => null,
                        }),
                        Cell::fromValue($patient?->identifier),
                        Cell::fromValue($patient?->phone),
                        Cell::fromValue($order->referring_doctor),
                        Cell::fromValue($order->orderExams
                            ->map(fn (OrderExam $orderExam): string => $orderExam->catalog_snapshot['exam']['name'] ?? $orderExam->exam->name)
                            ->implode(', ')),
                        Cell::fromValue($order->validatedBy?->name),
                        Cell::fromValue($order->validated_at?->toDateTimeImmutable(), $dateTimeStyle),
                    ]));

                    $count++;
                }
            });

        $writer->close();

        AuditLog::create([
            'user_id' => $actor->getKey(),
            'action' => 'orders_exported',
            'entity_type' => $actor->getMorphClass(),
            'entity_id' => $actor->getKey(),
            'metadata' => ['orders' => $count],
        ]);

        return $path;
    }
}
