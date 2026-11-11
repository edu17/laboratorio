@php
    $headerReserveMm = $headerReserveMm ?? 60;
    $footerReserveMm = $footerReserveMm ?? 20;
    $isPreview = $report['is_preview'] ?? false;
    $institution = $report['institution'];
    $patientName = trim(($report['patient']['first_names'] ?? '').' '.($report['patient']['last_names'] ?? ''));
    $hasOutOfRange = collect($report['exams'])
        ->flatMap(fn (array $exam): array => $exam['sections'])
        ->flatMap(fn (array $section): array => $section['rows'])
        ->contains(fn (array $row): bool => ! empty($row['flag']));
    $flagLabels = [
        \App\Actions\Results\ResolveParameterReference::FLAG_HIGH => 'Alto',
        \App\Actions\Results\ResolveParameterReference::FLAG_LOW => 'Bajo',
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte {{ $report['order']['number'] }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: {{ 8 + $headerReserveMm }}mm {{ \App\Actions\Orders\RenderValidatedOrderPdf::SIDE_MARGIN_MM }}mm
                {{ \App\Actions\Orders\RenderValidatedOrderPdf::BOTTOM_PAGE_MARGIN_MM + $footerReserveMm }}mm {{ \App\Actions\Orders\RenderValidatedOrderPdf::SIDE_MARGIN_MM }}mm;
        }

        body {
            color: #1c2730;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.6pt;
            line-height: 1.3;
        }

        p { margin: 0; }
        table { border-collapse: collapse; }
        h1, h2, h3 { margin: 0; }

        /* Encabezado que se repite en cada página */
        .report-header {
            position: fixed;
            top: -{{ $headerReserveMm }}mm;
            left: 0;
            right: 0;
        }
        .header-layout { width: 100%; }
        .header-layout td { vertical-align: middle; }
        .logo-cell { width: 20mm; padding-right: 3mm; }
        .header-logo { display: block; max-width: 20mm; max-height: 17mm; }
        .institution-name { color: #0f4c5c; font-size: 12.5pt; font-weight: bold; letter-spacing: 0.2pt; line-height: 1.15; }
        .institution-detail { margin-top: 0.6mm; color: #5d6d77; font-size: 7pt; }
        .qr-cell { width: 26mm; text-align: right; }
        .qr-corner { display: inline-block; width: 26mm; text-align: center; }
        .qr-corner img { display: block; width: 18mm; height: 18mm; margin: 0 auto; }
        .qr-caption { margin-top: 0.4mm; color: #5d6d77; font-size: 5.8pt; line-height: 1.2; }

        .header-rule { height: 0; margin: 1.2mm 0 0; border-top: 1.6pt solid #0f4c5c; }

        .details { width: 100%; margin: 0; background-color: #f2f6f7; border-bottom: 0.8pt solid #c9d6db; }
        .details td { padding: 1mm 2.2mm 0.8mm; vertical-align: top; }
        .details .label { display: block; color: #5d6d77; font-size: 6pt; letter-spacing: 0.4pt; text-transform: uppercase; }
        .details .value { display: block; margin-top: 0.3mm; color: #1c2730; font-size: 8.6pt; }
        .details .patient-name { font-size: 11pt; font-weight: bold; }
        .details .order-number { color: #0f4c5c; font-size: 10pt; font-weight: bold; }
        .details tr + tr td { border-top: 0.6pt solid #dde6e9; }

        /* Pie que se repite en cada página */
        .report-footer {
            position: fixed;
            bottom: -{{ $footerReserveMm }}mm;
            left: 0;
            right: 0;
            border-top: 1pt solid #0f4c5c;
        }
        .contact-footer { width: 100%; }
        .contact-footer td { padding: 1.4mm 0 0; vertical-align: top; color: #44535c; font-size: 6.8pt; line-height: 1.35; }
        .contact-footer .emergency { width: 30%; text-align: right; }
        .contact-footer .emergency strong { color: #0f4c5c; }
        .footer-meta { width: 100%; margin-top: 1mm; }
        .footer-meta td { color: #7a8891; font-size: 6.2pt; }
        .footer-meta .page-number { width: 25%; }

        /* Cuerpo */
        .preview-notice { margin-bottom: 2mm; padding: 1.5mm 2mm; color: #713f12; background-color: #fffbeb; border: 0.8pt solid #d6a640; font-size: 8pt; }

        .exam-title { margin: 4mm 0 1.5mm; padding-bottom: 1mm; border-bottom: 1.2pt solid #0f4c5c; page-break-after: avoid; }
        .exam-title h2 { color: #0f4c5c; font-size: 10.5pt; letter-spacing: 0.4pt; text-transform: uppercase; }
        .report-body > .exam-title:first-child { margin-top: 1mm; }

        .section { margin-bottom: 2.5mm; page-break-inside: avoid; }
        .section-long { page-break-inside: auto; }
        .section-title { margin: 0 0 0.8mm; padding: 0.6mm 0; color: #2f6f7a; font-size: 7.2pt; font-weight: bold; letter-spacing: 0.5pt; text-transform: uppercase; }

        .results { width: 100%; table-layout: fixed; }
        .results th, .results td { padding: 1.15mm 1.8mm; text-align: left; vertical-align: top; }
        .results thead { display: table-header-group; }
        .results th { color: #0f4c5c; background-color: #e7eff1; border-bottom: 0.8pt solid #0f4c5c; font-size: 6.6pt; letter-spacing: 0.4pt; text-transform: uppercase; }
        .results tbody td { border-bottom: 0.5pt solid #dfe6e9; }
        .results tr { page-break-inside: avoid; }
        .results .parameter-cell { color: #2b3a43; }
        .results .result-cell { font-weight: bold; }
        .results .muted-cell { color: #5d6d77; font-size: 7.8pt; }

        .out-of-range { color: #b42318; }
        .flag-label { color: #b42318; font-size: 6.4pt; font-weight: bold; text-transform: uppercase; }

        .paired-results td { padding: 1.15mm 1.8mm; border-bottom: 0.5pt solid #dfe6e9; vertical-align: top; }
        .paired-results .parameter-name { width: 22%; color: #5d6d77; }
        .paired-results .result-value { width: 28%; font-weight: bold; }
        .paired-results td:nth-child(2) { border-right: 0.5pt solid #dfe6e9; }
        .paired-results td:nth-child(3) { padding-left: 3mm; }
        .paired-results .result-detail { display: block; margin-top: 0.3mm; color: #5d6d77; font-size: 6.8pt; font-weight: normal; }
        .paired-results .empty-pair { border-bottom-color: transparent; }

        .matrix th, .matrix td { border: 0.5pt solid #c9d6db; }
        .matrix th { border-bottom: 0.8pt solid #0f4c5c; }
        .matrix th:not(:first-child), .matrix td:not(:first-child) { text-align: center; }
        .matrix td { padding-top: 0.8mm; padding-bottom: 0.8mm; font-size: 7.8pt; }
        .matrix td:first-child { color: #2b3a43; font-style: italic; }
        .matrix td:not(:first-child) { font-weight: bold; }
        .matrix-empty-status { padding: 1.5mm 2mm; color: #174c45; background-color: #e8f3f0; border: 0.8pt solid #aac9c1; font-size: 8pt; }

        /* Cierre: leyenda, fin del reporte, validación y firmas (una sola vez) */
        .legend { margin-top: 2mm; color: #5d6d77; font-size: 6.8pt; }
        .report-signoff { margin-top: 5mm; page-break-inside: avoid; }
        .end-marker { width: 100%; margin-bottom: 3mm; }
        .end-marker td { vertical-align: middle; }
        .end-marker .rule { border-top: 0.6pt solid #b8c6cc; }
        .end-marker .text { width: 30mm; color: #7a8891; font-size: 6.6pt; letter-spacing: 0.6pt; text-align: center; text-transform: uppercase; }
        .validation-metadata { width: 100%; margin-bottom: 2mm; }
        .validation-metadata td { vertical-align: middle; color: #34434c; font-size: 7.6pt; }
        .validation-metadata td:last-child { text-align: right; }
        .validated-state { display: inline-block; padding: 0.5mm 2mm; color: #174c45; background-color: #e8f3f0; border: 0.8pt solid #aac9c1; font-size: 7.4pt; font-weight: bold; }
        .report-assets { width: 100%; table-layout: fixed; }
        .report-assets td { vertical-align: bottom; text-align: center; }
        .signature-cell { width: 40%; padding: 0 3mm; }
        .seal-cell { width: 20%; }
        .signature-image { display: block; max-width: 46mm; max-height: 15mm; margin: 0 auto 1mm; }
        .seal-image { display: block; max-width: 20mm; max-height: 20mm; margin: 0 auto; }
        .signature-label { padding-top: 1mm; border-top: 0.6pt solid #829099; color: #34434c; font-size: 7pt; }
        .seal-label { margin-top: 0.8mm; color: #5d6d77; font-size: 6.4pt; }
        .placeholder-cell { padding: 6mm 2mm 1mm; color: #7a8891; border-top: 0.6pt dashed #b8c6cc; font-size: 7pt; }

        /* Heces en una sola página */
        .compact-report .exam-title { margin: 2mm 0 0.8mm; }
        .compact-report .section { margin-bottom: 1mm; }
        .compact-report .section-title { margin-bottom: 0.3mm; padding: 0.3mm 0; }
        .compact-report .results th { padding-top: 0.6mm; padding-bottom: 0.6mm; }
        .compact-report .results td,
        .compact-report .paired-results td { padding-top: 0.4mm; padding-bottom: 0.4mm; font-size: 8pt; }
        .compact-report .matrix td { padding-top: 0.15mm; padding-bottom: 0.15mm; font-size: 7.6pt; }
        .compact-report .report-signoff { margin-top: 2mm; }
        .compact-report .end-marker { margin-bottom: 1.5mm; }
        .compact-report .validation-metadata { margin-bottom: 1mm; }
        .compact-report .signature-image { max-height: 10mm; }
        .compact-report .seal-image { max-width: 15mm; max-height: 15mm; }
    </style>
</head>
<body class="{{ count($report['exams']) === 1 && ($report['exams'][0]['code'] ?? '') === 'HECES' ? 'compact-report' : '' }}">
    @include('reports.footer', ['report' => $report])

    <header class="report-header">
        <table class="header-layout">
            <tbody>
                <tr>
                    @if (filled($institution['logo_data_uri'] ?? null))
                        <td class="logo-cell">
                            <img class="header-logo" src="{{ $institution['logo_data_uri'] }}" alt="Logotipo institucional">
                        </td>
                    @endif
                    <td>
                        <h1 class="institution-name">{{ $institution['name'] }}</h1>
                        @if (filled($institution['subtitle']))
                            <p class="institution-detail">{{ $institution['subtitle'] }}</p>
                        @endif
                        <p class="institution-detail">Registro Sanitario DRACES: {{ $institution['sanitary_registration'] ?: 'Sin registrar' }}</p>
                    </td>
                    <td class="qr-cell">
                        @if (isset($report['public_access']['qr_code']))
                            <div class="qr-corner">
                                <img src="{{ $report['public_access']['qr_code'] }}" alt="QR para consultar el reporte">
                                <p class="qr-caption">Escanea para consultar<br>y verificar este reporte</p>
                            </div>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="header-rule"></div>

        <table class="details">
            <tbody>
                <tr>
                    <td colspan="2">
                        <span class="label">Paciente</span>
                        <span class="value patient-name">{{ $patientName }}</span>
                    </td>
                    <td>
                        <span class="label">ID de paciente</span>
                        <span class="value">{{ filled($report['patient']['identifier'] ?? null) ? $report['patient']['identifier'] : 'Sin registrar' }}</span>
                    </td>
                    <td colspan="2">
                        <span class="label">Orden No.</span>
                        <span class="value order-number">{{ $report['order']['number'] }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="width: 32%;">
                        <span class="label">Médico remitente</span>
                        <span class="value">{{ filled($report['order']['referring_doctor'] ?? null) ? $report['order']['referring_doctor'] : 'Sin registrar' }}</span>
                    </td>
                    <td style="width: 13%;">
                        <span class="label">Edad</span>
                        <span class="value">{{ $report['patient']['age_display'] }}</span>
                    </td>
                    <td style="width: 17%;">
                        <span class="label">Sexo</span>
                        <span class="value">{{ $report['patient']['gender_display'] }}</span>
                    </td>
                    <td style="width: 19%;">
                        <span class="label">Fecha de orden</span>
                        <span class="value">{{ $report['order']['ordered_at_display'] ?? 'Sin registrar' }}</span>
                    </td>
                    <td style="width: 19%;">
                        <span class="label">Fecha de validación</span>
                        <span class="value">{{ $report['validation']['validated_at_display'] }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </header>

    <main class="report-body">
        @if ($isPreview)
            <p class="preview-notice">BORRADOR — Datos ficticios. No es un resultado clínico. Se muestran las secciones y parámetros activos guardados.</p>
            @foreach ($report['preview_notes'] ?? [] as $note)
                <p class="preview-notice">{{ $note }}</p>
            @endforeach
        @endif

        @foreach ($report['exams'] as $exam)
            <header class="exam-title">
                <h2>{{ $exam['name'] }}</h2>
            </header>

            @if ($isPreview && $exam['sections'] === [])
                <p class="preview-notice">Este examen todavía no tiene secciones activas configuradas.</p>
            @endif

            @foreach ($exam['sections'] as $section)
                @php
                    $showUnit = collect($section['rows'])->contains(fn (array $row): bool => filled($row['unit'] ?? null));
                    $showReference = collect($section['rows'])->contains(fn (array $row): bool => filled($row['reference'] ?? null));
                    $columnWidths = match (true) {
                        $showUnit && $showReference => ['parameter' => 31, 'result' => 21, 'unit' => 13, 'reference' => 35],
                        $showReference => ['parameter' => 35, 'result' => 25, 'reference' => 40],
                        $showUnit => ['parameter' => 50, 'result' => 30, 'unit' => 20],
                        default => ['parameter' => 40, 'result' => 60],
                    };
                @endphp
                <section class="section {{ count($section['rows']) > 20 ? 'section-long' : '' }}">
                    <h3 class="section-title">{{ $section['name'] }}</h3>

                    @if ($isPreview && $section['rows'] === [])
                        <p class="preview-notice">Esta sección todavía no tiene parámetros activos.</p>
                    @elseif ($section['is_matrix'] && $section['status'] === \App\Models\OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS)
                        <p class="matrix-empty-status">Completada sin hallazgos.</p>
                    @elseif (($section['layout'] ?? 'standard') === 'paired' && ! $section['is_matrix'])
                        <table class="results paired-results">
                            <tbody>
                                @foreach (array_chunk($section['rows'], 2) as $rowPair)
                                    <tr>
                                        @foreach ([0, 1] as $pairIndex)
                                            @php($row = $rowPair[$pairIndex] ?? null)
                                            @if ($row)
                                                <td class="parameter-name">{{ $row['name'] }}</td>
                                                <td class="result-value">
                                                    @if (! empty($row['flag']))
                                                        <strong class="out-of-range">*{{ $row['result'] }}</strong>
                                                        <span class="flag-label">{{ $flagLabels[$row['flag']] ?? '' }}</span>
                                                    @else
                                                        {{ $row['result'] }}
                                                    @endif
                                                    @if (filled($row['unit']))
                                                        <span class="result-detail">{{ $row['unit'] }}</span>
                                                    @endif
                                                    @if (filled($row['reference']))
                                                        <span class="result-detail">{{ $row['reference'] }}</span>
                                                    @endif
                                                </td>
                                            @else
                                                <td class="empty-pair" colspan="2"></td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @elseif ($section['is_matrix'])
                        <table class="results matrix">
                            <colgroup>
                                <col style="width: 29%;">
                                @foreach (array_slice($section['columns'], 1) as $column)
                                    <col style="width: {{ 71 / max(1, count($section['columns']) - 1) }}%;">
                                @endforeach
                            </colgroup>
                            <thead>
                                <tr>
                                    @foreach ($section['columns'] as $column)
                                        <th style="width: {{ $loop->first ? 29 : 71 / max(1, count($section['columns']) - 1) }}%;">{{ $column }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($section['rows'] as $row)
                                    <tr>
                                        <td style="width: 29%;">{{ $row['name'] }}</td>
                                        @foreach ($row['cells'] as $cell)
                                            <td style="width: {{ 71 / max(1, count($section['columns']) - 1) }}%;">{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <table class="results">
                            <thead>
                                <tr>
                                    <th style="width: {{ $columnWidths['parameter'] }}%;">Análisis</th>
                                    <th style="width: {{ $columnWidths['result'] }}%;">Resultado</th>
                                    @if ($showUnit)
                                        <th style="width: {{ $columnWidths['unit'] }}%;">Unidad</th>
                                    @endif
                                    @if ($showReference)
                                        <th style="width: {{ $columnWidths['reference'] }}%;">Valores de referencia</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($section['rows'] as $row)
                                    <tr>
                                        <td class="parameter-cell">{{ $row['name'] }}</td>
                                        <td class="result-cell">
                                            @if (! empty($row['flag']))
                                                <strong class="out-of-range">*{{ $row['result'] }}</strong>
                                                <span class="flag-label">{{ $flagLabels[$row['flag']] ?? '' }}</span>
                                            @else
                                                {{ $row['result'] }}
                                            @endif
                                        </td>
                                        @if ($showUnit)
                                            <td class="muted-cell">{{ $row['unit'] }}</td>
                                        @endif
                                        @if ($showReference)
                                            <td class="muted-cell">{{ $row['reference'] }}</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            @endforeach
        @endforeach

        @if ($hasOutOfRange)
            <p class="legend">* Resultado fuera del rango de referencia indicado para el paciente.</p>
        @endif

        @include('reports.signoff', ['report' => $report, 'isPreview' => $isPreview])
    </main>
</body>
</html>
