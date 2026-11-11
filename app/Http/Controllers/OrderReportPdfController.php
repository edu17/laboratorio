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
        return $renderPdf->response(
            $buildReportData->handle($order, $request->user()),
            $request->boolean('download'),
        );
    }
}
