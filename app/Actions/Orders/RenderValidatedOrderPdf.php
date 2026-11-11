<?php

namespace App\Actions\Orders;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use DOMElement;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Dompdf\Frame;

class RenderValidatedOrderPdf
{
    /** Deben coincidir con los márgenes de @page en resources/views/reports/order.blade.php. */
    public const SIDE_MARGIN_MM = 14;

    public const BOTTOM_PAGE_MARGIN_MM = 15;

    /**
     * Renderiza dos veces: la primera mide el encabezado y el pie que se repiten en cada página,
     * la segunda reserva exactamente ese espacio en los márgenes.
     *
     * @param  array<string, mixed>  $report
     */
    public function handle(array $report): PdfDocument
    {
        $heightsInPoints = ['report-header' => 0.0, 'report-footer' => 0.0];
        $measurement = $this->document($report, 60.0, 20.0);
        $measurement->getDomPDF()->setCallbacks([
            [
                'event' => 'end_frame',
                'f' => function (Frame $frame) use (&$heightsInPoints): void {
                    $node = $frame->get_node();

                    if (! $node instanceof DOMElement) {
                        return;
                    }

                    foreach (array_keys($heightsInPoints) as $class) {
                        if (in_array($class, explode(' ', $node->getAttribute('class')), true)) {
                            $heightsInPoints[$class] = max($heightsInPoints[$class], (float) $frame->get_border_box()['h']);
                        }
                    }
                },
            ],
        ]);
        $measurement->output();

        $pdf = $this->document(
            $report,
            $this->toReservedMillimeters($heightsInPoints['report-header']),
            $this->toReservedMillimeters($heightsInPoints['report-footer']),
        );
        $pdf->getDomPDF()->setCallbacks([
            ['event' => 'end_document', 'f' => $this->writePageNumber(...)],
        ]);

        return $pdf;
    }

    /**
     * Escribe "Página X de Y" en el pie de cada página. El total solo se conoce al terminar de renderizar,
     * por eso se dibuja sobre el lienzo y no con CSS (DomPDF no resuelve counter(pages)).
     */
    private function writePageNumber(int $pageNumber, int $pageCount, Canvas $canvas, FontMetrics $fontMetrics): void
    {
        $font = $fontMetrics->getFont('DejaVu Sans');
        $size = 6.2;
        $text = "Página {$pageNumber} de {$pageCount}";
        $rightMarginPoints = self::SIDE_MARGIN_MM * 72 / 25.4;
        $top = $canvas->get_height() - (self::BOTTOM_PAGE_MARGIN_MM * 72 / 25.4) - $size - 3;

        $canvas->text(
            $canvas->get_width() - $rightMarginPoints - $fontMetrics->getTextWidth($text, $font, $size),
            $top,
            $text,
            $font,
            $size,
            [0.2, 0.25, 0.3],
        );
    }

    private function toReservedMillimeters(float $points): float
    {
        return ceil($points * 25.4 / 72) + 3;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function document(array $report, float $headerReserveMm, float $footerReserveMm): PdfDocument
    {
        return Pdf::loadView('reports.order', [
            'report' => $report,
            'headerReserveMm' => $headerReserveMm,
            'footerReserveMm' => $footerReserveMm,
        ])
            ->setPaper('letter', 'portrait')
            ->setOption([
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'isJavascriptEnabled' => false,
            ]);
    }
}
