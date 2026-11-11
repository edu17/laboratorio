<?php

namespace App\Actions\Orders;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use DOMElement;
use Dompdf\Frame;

class RenderValidatedOrderPdf
{
    /**
     * @param  array<string, mixed>  $report
     */
    public function handle(array $report): PdfDocument
    {
        $footerHeightPoints = 0.0;
        $measurement = $this->document($report, 50.0);
        $measurement->getDomPDF()->setCallbacks([
            [
                'event' => 'end_frame',
                'f' => function (Frame $frame) use (&$footerHeightPoints): void {
                    $node = $frame->get_node();

                    if ($node instanceof DOMElement && in_array('report-footer', explode(' ', $node->getAttribute('class')), true)) {
                        $footerHeightPoints = max($footerHeightPoints, (float) $frame->get_border_box()['h']);
                    }
                },
            ],
        ]);
        $measurement->output();

        $reservedMillimeters = ceil($footerHeightPoints * 25.4 / 72) + 3;

        return $this->document($report, $reservedMillimeters);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function document(array $report, float $footerReserveMm): PdfDocument
    {
        return Pdf::loadView('reports.order', ['report' => $report, 'footerReserveMm' => $footerReserveMm])
            ->setPaper('letter', 'portrait')
            ->setOption([
                'isRemoteEnabled' => false,
                'isPhpEnabled' => false,
                'isJavascriptEnabled' => false,
            ]);
    }
}
