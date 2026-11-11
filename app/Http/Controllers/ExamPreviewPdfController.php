<?php

namespace App\Http\Controllers;

use App\Actions\Exams\BuildExamPreviewReportData;
use App\Actions\Orders\RenderValidatedOrderPdf;
use App\Models\Exam;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExamPreviewPdfController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Exam $exam, BuildExamPreviewReportData $buildPreview, RenderValidatedOrderPdf $renderPdf): Response
    {
        $report = $buildPreview->handle($exam, $request->user());
        $response = $renderPdf->handle($report)->stream('vista-previa-examen.pdf');
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        return $response;
    }
}
