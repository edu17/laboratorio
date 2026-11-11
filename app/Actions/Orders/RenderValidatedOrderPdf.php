<?php

namespace App\Actions\Orders;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use DOMElement;
use Dompdf\Canvas;
use Dompdf\FontMetrics;
use Dompdf\Frame;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

class RenderValidatedOrderPdf
{
    /** Deben coincidir con los márgenes de @page en resources/views/reports/order.blade.php. */
    public const SIDE_MARGIN_MM = 14;

    public const BOTTOM_PAGE_MARGIN_MM = 15;

    public const CACHE_DIRECTORY = 'report-cache';

    /**
     * Respuesta HTTP del PDF (en línea o como descarga), servida desde la caché y sin guardar en el navegador.
     *
     * @param  array<string, mixed>  $report
     */
    public function response(array $report, bool $download): Response
    {
        $filename = sprintf('reporte-%s-v%d.pdf', $report['order']['number'], $report['validation']['version']);

        return response($this->cachedOutput($report), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($download ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE, $filename),
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    /**
     * Devuelve los bytes del PDF, reutilizando el archivo ya generado si el reporte no cambió.
     * La clave es una huella de todo el contenido salvo la hora de generación: si se reabre, revalida
     * o cambia cualquier dato, la huella cambia y se genera de nuevo.
     *
     * @param  array<string, mixed>  $report
     */
    public function cachedOutput(array $report): string
    {
        $content = $report;
        unset($content['generated_at']);
        $path = self::CACHE_DIRECTORY.'/'.hash('sha256', json_encode($content, JSON_UNESCAPED_UNICODE)).'.pdf';
        $disk = Storage::disk('local');

        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        $bytes = $this->handle($report)->output();
        $disk->put($path, $bytes);

        return $bytes;
    }

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
                'isFontSubsettingEnabled' => true,
            ]);
    }
}
