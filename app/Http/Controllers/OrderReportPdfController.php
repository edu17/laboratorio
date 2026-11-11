<?php

namespace App\Http\Controllers;

use App\Actions\Orders\BuildValidatedOrderReportData;
use App\Actions\Orders\RenderValidatedOrderPdf;
use App\Models\Order;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderReportPdfController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Order $order, BuildValidatedOrderReportData $buildReportData, RenderValidatedOrderPdf $renderPdf): Response
    {
        $report = $buildReportData->handle($order, $request->user());
        $filename = sprintf('reporte-%s-v%d.pdf', $report['order']['number'], $report['validation']['version']);

        $pdf = $renderPdf->handle($report);

        $response = $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
