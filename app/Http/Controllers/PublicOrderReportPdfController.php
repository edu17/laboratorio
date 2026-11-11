<?php

namespace App\Http\Controllers;

use App\Actions\Orders\BuildValidatedOrderReportData;
use App\Actions\Orders\RenderValidatedOrderPdf;
use App\Models\ReportAccessToken;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicOrderReportPdfController extends Controller
{
    public function __invoke(Request $request, string $token, BuildValidatedOrderReportData $buildReportData, RenderValidatedOrderPdf $renderPdf): Response
    {
        $accessToken = ReportAccessToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
        $report = $buildReportData->handlePublicAccess($accessToken);
        $filename = sprintf('reporte-%s-v%d.pdf', $report['order']['number'], $report['validation']['version']);

        $pdf = $renderPdf->handle($report);

        $response = $request->boolean('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);

        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
