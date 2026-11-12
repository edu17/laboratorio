@php
    $headerReserveMm = $headerReserveMm ?? 60;
    $footerReserveMm = $footerReserveMm ?? 20;
    $isPreview = $report['is_preview'] ?? false;
    $institution = $report['institution'];
    $patientName = trim(($report['patient']['first_names'] ?? '').' '.($report['patient']['last_names'] ?? ''));
    $allRows = collect($report['exams'])
        ->flatMap(fn (array $exam): array => $exam['sections'])
        ->reject(fn (array $section): bool => $section['is_matrix'] ?? false)
        ->flatMap(fn (array $section): array => $section['rows']);
    $positiveValues = ['positivo', 'reactivo'];
    $qualitativeValues = ['negativo', 'positivo', 'reactivo', 'no reactivo'];
    $outOfRangeCount = $allRows->filter(fn (array $row): bool => ! empty($row['flag']))->count();
    $positiveCount = $allRows->filter(fn (array $row): bool => in_array(mb_strtolower(trim($row['result'] ?? '')), $positiveValues, true))->count();
    $flagLabels = [
        \App\Actions\Results\ResolveParameterReference::FLAG_HIGH => '▲ Alto',
        \App\Actions\Results\ResolveParameterReference::FLAG_LOW => '▼ Bajo',
    ];
    $rangeHintShown = false;
    $rangeMarker = fn (string $color): string => 'data:image/svg+xml;base64,'.base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 14 14"><circle cx="7" cy="7" r="5.6" fill="'.$color.'" stroke="#ffffff" stroke-width="2"/></svg>'
    );
    $rangeMarkers = ['normal' => $rangeMarker('#1d3b64'), 'alert' => $rangeMarker('#e46a1c')];
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

        body { color: #18212c; font-family: DejaVu Sans, sans-serif; font-size: 8.6pt; line-height: 1.3; }
        p { margin: 0; }
        table { border-collapse: collapse; }
        h1, h2, h3 { margin: 0; }

        .muted { color: #4f5d6b; }
        .label { display: block; color: #4f5d6b; font-size: 6.3pt; letter-spacing: 0.4pt; text-transform: uppercase; }
        .chip { padding: 0.3mm 1.8mm; border-radius: 2mm; font-size: 7pt; font-weight: bold; white-space: nowrap; }
        .chip-normal { color: #34404d; background-color: #eef2f6; }
        .chip-alert { color: #9a3b0c; background-color: #fff1e6; }

        /* Encabezado que se repite en cada página */
        .report-header { position: fixed; top: -{{ $headerReserveMm }}mm; left: 0; right: 0; }
        .accent-bar { height: 2mm; background-color: #1d3b64; }
        .header-layout { width: 100%; margin-top: 3mm; }
        .header-layout td { vertical-align: middle; }
        .logo-cell { width: 22mm; padding-right: 3mm; }
        .header-logo { display: block; max-width: 21mm; max-height: 21mm; }
        .logo-monogram { width: 12mm; height: 12mm; border-radius: 2.5mm; background-color: #1d3b64; color: #fff; font-size: 11pt; font-weight: bold; line-height: 12mm; text-align: center; }
        .institution-name { color: #1d3b64; font-size: 11pt; font-weight: bold; line-height: 1.2; }
        .institution-detail { margin-top: 0.5mm; color: #4f5d6b; font-size: 7.2pt; }
        .order-cell { width: 40mm; text-align: right; }
        .order-kicker { color: #4f5d6b; font-size: 6.3pt; letter-spacing: 0.4pt; text-transform: uppercase; }
        .order-number-big { margin-top: 0.4mm; color: #1d3b64; font-size: 11pt; font-weight: bold; }
        .qr-caption { margin-top: 0.4mm; color: #4f5d6b; font-size: 6.3pt; }
        .qr-cell { width: 19mm; padding-left: 2.5mm; text-align: right; }
        .qr-corner img { display: block; width: 17mm; height: 17mm; }

        .details { width: 100%; margin-top: 3mm; border: 0.8pt solid #d5dde6; table-layout: fixed; }
        .details td { padding: 1.4mm 2.5mm 1.2mm; vertical-align: top; border-right: 0.6pt solid #e3e9ef; }
        .details td.last { border-right: 0; }
        .details tr + tr td { border-top: 0.6pt solid #e3e9ef; }
        .details .value { display: block; margin-top: 0.3mm; font-size: 8.6pt; }
        .details .patient-name { font-size: 10.5pt; font-weight: bold; }
        .details .inline-label { color: #4f5d6b; }

        /* Pie que se repite en cada página */
        .report-footer { position: fixed; bottom: -{{ $footerReserveMm }}mm; left: 0; right: 0; }
        .contact-footer { width: 100%; margin-top: 1.5mm; border-top: 0.8pt solid #d5dde6; }
        .contact-footer td { padding-top: 1.6mm; vertical-align: top; color: #4f5d6b; font-size: 6.8pt; line-height: 1.45; }
        .contact-footer .footer-meta { width: 40%; text-align: right; }

        /* Cuerpo */
        .preview-notice { margin-bottom: 2mm; padding: 1.5mm 2mm; color: #713f12; background-color: #fffbeb; border: 0.8pt solid #d6a640; font-size: 8pt; }

        .exam-title { margin: 5mm 0 1mm; page-break-after: avoid; }
        .exam-title h2 { color: #1d3b64; font-size: 10.5pt; }
        .exam-title .range-hint { float: right; margin-top: 0.8mm; color: #5c6a78; font-size: 6.6pt; font-weight: normal; }
        .report-body > .exam-title:first-child { margin-top: 1mm; }
        .exam-title.separate-page { margin-top: 1mm; page-break-before: always; }

        .section { margin-bottom: 2.5mm; page-break-inside: avoid; }
        .section-long { page-break-inside: auto; }
        .section-title { margin: 1.5mm 0 0.5mm; color: #4f5d6b; font-size: 7pt; font-weight: bold; letter-spacing: 0.4pt; text-transform: uppercase; page-break-after: avoid; }

        .results { width: 100%; table-layout: fixed; }
        .results th { padding: 1mm 1.8mm; color: #4f5d6b; border-bottom: 1.1pt solid #1d3b64; font-size: 6.4pt; letter-spacing: 0.4pt; text-align: left; text-transform: uppercase; }
        .results td { padding: 1.4mm 1.8mm; border-bottom: 0.6pt solid #e3e9ef; vertical-align: middle; }
        .results th:first-child, .results td:first-child { padding-left: 0; }
        .results th:last-child, .results td:last-child { padding-right: 0; }
        .results thead { display: table-header-group; }
        .results tr { page-break-inside: avoid; }
        .results .reference-line { display: block; margin-top: 0.3mm; color: #5c6a78; font-size: 6.9pt; }
        .results .result-value { font-size: 9.4pt; font-weight: bold; }
        .results .unit { color: #5c6a78; font-size: 7pt; }
        .results .state-cell { text-align: right; }

        .out-of-range { color: #b54708; }

        /* Barra de rango: pista gris, zona normal azul y marcador */
        .range { height: 3.6mm; }
        .range-track { height: 1.9mm; margin-top: 0.85mm; background-color: #eef2f6; border-radius: 1mm; }
        .range-normal { height: 1.9mm; background-color: #d3e2f3; }
        .range-marker-row { height: 3.6mm; margin-top: -2.75mm; }
        .range-marker { display: block; width: 3.6mm; height: 3.6mm; margin-left: -1.8mm; }

        /* Resultados cualitativos en tarjetas */
        .qualitative-cards { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 2mm 1.6mm; margin: -1.6mm -2mm 0; }
        .qualitative-cards td { padding: 2mm 2.6mm; border: 0.8pt solid #e3e9ef; border-radius: 2mm; vertical-align: middle; }
        .qualitative-cards td.positive { border: 1.2pt solid #e46a1c; }
        .qualitative-cards td.empty-card { border-color: transparent; }
        .qualitative-cards .card-chip { float: right; }

        /* Heces y orina: pares de campos */
        .paired-results td { padding: 1.2mm 1.8mm; border-bottom: 0.6pt solid #e3e9ef; vertical-align: top; }
        .paired-results .parameter-name { width: 22%; padding-left: 0; color: #4f5d6b; }
        .paired-results .result-value { width: 28%; font-weight: bold; }
        .paired-results td:nth-child(2) { border-right: 0.6pt solid #e3e9ef; }
        .paired-results td:nth-child(3) { padding-left: 3mm; }
        .paired-results .result-detail { display: block; margin-top: 0.3mm; color: #5c6a78; font-size: 6.8pt; font-weight: normal; }
        .paired-results .empty-pair { border-bottom-color: transparent; }

        .matrix { width: 100%; table-layout: fixed; }
        .matrix th, .matrix td { padding: 0.8mm 1.8mm; border: 0.6pt solid #d5dde6; }
        .matrix th { color: #4f5d6b; background-color: #f5f7fa; border-bottom: 1.1pt solid #1d3b64; font-size: 6.4pt; letter-spacing: 0.4pt; text-align: left; text-transform: uppercase; }
        .matrix th:not(:first-child), .matrix td:not(:first-child) { text-align: center; }
        .matrix td { font-size: 7.8pt; }
        .matrix td:first-child { font-style: italic; }
        .matrix td:not(:first-child) { font-weight: bold; }
        .matrix-empty-status { padding: 1.5mm 2mm; color: #34404d; background-color: #eef2f6; border-radius: 1.5mm; font-size: 8pt; }

        /* Validación, firmas y sello: parte del pie fijo, en cada página */
        .signoff-box { width: 100%; table-layout: fixed; border: 0.8pt solid #d5dde6; border-radius: 2mm; }
        .signoff-box td { padding: 1.6mm 2.5mm; vertical-align: bottom; }
        .signoff-box .validation-cell { width: 26%; font-size: 7.2pt; line-height: 1.4; vertical-align: middle; }
        .signoff-box .signature-cell { width: 25.5%; text-align: center; }
        .signoff-box .seal-cell { width: 23%; text-align: center; vertical-align: middle; }
        .validated-state { display: inline-block; margin-top: 0.8mm; padding: 0.4mm 1.8mm; color: #174c45; background-color: #e3f1ec; border-radius: 1.5mm; font-size: 7pt; font-weight: bold; }
        .signature-image { display: block; max-width: 56mm; max-height: 19mm; margin: 0 auto 0.8mm; }
        .signature-label { padding-top: 0.6mm; border-top: 0.6pt solid #9aa7b4; color: #34404d; font-size: 6.9pt; }
        .seal-image { display: block; max-width: 40mm; max-height: 21mm; margin: 0 auto; }
        .seal-label { margin-top: 0.4mm; color: #5c6a78; font-size: 6.2pt; }
        .placeholder-cell { padding-top: 6mm; color: #5c6a78; border-top: 0.6pt dashed #9aa7b4; font-size: 7pt; }
        .end-marker { margin-top: 3mm; color: #5c6a78; font-size: 6.6pt; letter-spacing: 0.8pt; text-align: center; text-transform: uppercase; page-break-before: avoid; }

        /* Heces en una sola página */
        .compact-report .exam-title { margin: 2.5mm 0 0.6mm; }
        .compact-report .section { margin-bottom: 1mm; }
        .compact-report .section-title { margin-top: 0.8mm; }
        .compact-report .paired-results td { padding-top: 0.45mm; padding-bottom: 0.45mm; font-size: 8pt; }
        .compact-report .matrix th { padding-top: 0.6mm; padding-bottom: 0.6mm; }
        .compact-report .matrix td { padding-top: 0.15mm; padding-bottom: 0.15mm; font-size: 7.6pt; }
        .compact-report .end-marker { margin-top: 1.5mm; }
    </style>
</head>
<body class="{{ count($report['exams']) === 1 && ($report['exams'][0]['code'] ?? '') === 'HECES' ? 'compact-report' : '' }}">
    @include('reports.footer', ['report' => $report])

    <header class="report-header">
        <div class="accent-bar"></div>
        <table class="header-layout">
            <tbody>
                <tr>
                    <td class="logo-cell">
                        @if (filled($institution['institutional_seal_data_uri'] ?? null))
                            <img class="header-logo" src="{{ $institution['institutional_seal_data_uri'] }}" alt="Sello institucional">
                        @elseif (filled($institution['logo_data_uri'] ?? null))
                            <img class="header-logo" src="{{ $institution['logo_data_uri'] }}" alt="Logotipo institucional">
                        @else
                            <div class="logo-monogram">{{ mb_strtoupper(mb_substr($institution['name'], 0, 1)) }}</div>
                        @endif
                    </td>
                    <td>
                        <h1 class="institution-name">{{ $institution['name'] }}</h1>
                        <p class="institution-detail">Registro Sanitario DRACES {{ $institution['sanitary_registration'] ?: 'sin registrar' }}@if (filled($institution['subtitle'])) · {{ $institution['subtitle'] }}@endif</p>
                    </td>
                    <td class="order-cell">
                        <p class="order-kicker">Reporte de resultados</p>
                        <p class="order-number-big">{{ $report['order']['number'] }}</p>
                        @if (isset($report['public_access']['qr_code']))
                            <p class="qr-caption">Escanea para consultar y verificar</p>
                        @endif
                    </td>
                    @if (isset($report['public_access']['qr_code']))
                        <td class="qr-cell">
                            <div class="qr-corner">
                                <img src="{{ $report['public_access']['qr_code'] }}" alt="QR para consultar el reporte">
                            </div>
                        </td>
                    @endif
                </tr>
            </tbody>
        </table>

        <table class="details">
            <colgroup>
                <col style="width: 25%;">
                <col style="width: 25%;">
                <col style="width: 18%;">
                <col style="width: 14%;">
                <col style="width: 9%;">
                <col style="width: 9%;">
            </colgroup>
            <tbody>
                <tr>
                    <td colspan="2">
                        <span class="label">Paciente</span>
                        <span class="value patient-name">{{ $patientName }}</span>
                    </td>
                    <td>
                        <span class="label">Edad · Sexo</span>
                        <span class="value">{{ $report['patient']['age_display'] }}<br>{{ $report['patient']['gender_display'] }}</span>
                    </td>
                    <td>
                        <span class="label">ID de paciente</span>
                        <span class="value">{{ filled($report['patient']['identifier'] ?? null) ? $report['patient']['identifier'] : 'Sin registrar' }}</span>
                    </td>
                    <td colspan="2" class="last">
                        <span class="label">Médico remitente</span>
                        <span class="value">{{ filled($report['order']['referring_doctor'] ?? null) ? $report['order']['referring_doctor'] : 'Sin registrar' }}</span>
                    </td>
                </tr>
                <tr>
                    <td colspan="3">
                        <span class="value"><span class="inline-label">Orden:</span> {{ $report['order']['ordered_at_display'] ?? 'Sin registrar' }} · <span class="inline-label">Validación:</span> {{ $report['validation']['validated_at_display'] }}</span>
                    </td>
                    <td colspan="3" class="last">
                        <span class="value">
                            <span class="inline-label">Resumen:</span>
                            @if ($outOfRangeCount === 0 && $positiveCount === 0)
                                <span class="chip chip-normal">Sin valores marcados</span>
                            @endif
                            @if ($outOfRangeCount > 0)
                                <span class="chip chip-alert">{{ $outOfRangeCount }} fuera de rango</span>
                            @endif
                            @if ($positiveCount > 0)
                                <span class="chip chip-alert">{{ $positiveCount }} {{ $positiveCount === 1 ? 'positivo' : 'positivos' }}</span>
                            @endif
                        </span>
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
            @php
                $examHasRange = collect($exam['sections'])->flatMap(fn (array $section): array => $section['rows'])->contains(fn (array $row): bool => ! empty($row['indicator']));
                $showRangeHint = $examHasRange && ! $rangeHintShown;
                $rangeHintShown = $rangeHintShown || $examHasRange;
                $startsNewPage = ! $loop->first && (($exam['prints_on_separate_page'] ?? false) || ($report['exams'][$loop->index - 1]['prints_on_separate_page'] ?? false));
            @endphp
            <header @class(['exam-title', 'separate-page' => $startsNewPage])>
                @if ($showRangeHint)
                    <span class="range-hint">La barra muestra el resultado frente al rango de referencia</span>
                @endif
                <h2>{{ $exam['name'] }}</h2>
            </header>

            @if ($isPreview && $exam['sections'] === [])
                <p class="preview-notice">Este examen todavía no tiene secciones activas configuradas.</p>
            @endif

            @foreach ($exam['sections'] as $section)
                @php
                    $rows = $section['rows'];
                    $isQualitative = ! ($section['is_matrix'] ?? false) && $rows !== [] && collect($rows)->every(fn (array $row): bool => blank($row['unit'] ?? null) && blank($row['reference'] ?? null) && in_array(mb_strtolower(trim($row['result'] ?? '')), $qualitativeValues, true));
                    $showRange = collect($rows)->contains(fn (array $row): bool => ! empty($row['indicator']));
                    $showUnit = collect($rows)->contains(fn (array $row): bool => filled($row['unit'] ?? null));
                    $columnWidths = $showRange ? ['name' => 33, 'result' => 19, 'range' => 33, 'state' => 15] : ['name' => 50, 'result' => 50];
                    $showSectionTitle = count($exam['sections']) > 1 || ($section['is_matrix'] ?? false);
                    $longTextRows = collect($rows)->contains(fn (array $row): bool => mb_strlen($row['result'] ?? '') > 120);
                    $keepTogether = ($section['is_matrix'] ?? false) ? count($rows) <= 20 : count($rows) <= 12 && ! $longTextRows;
                @endphp
                <section class="section {{ $keepTogether ? '' : 'section-long' }}">
                    @if ($showSectionTitle)
                        <h3 class="section-title">{{ $section['name'] }}</h3>
                    @endif

                    @if ($isPreview && $rows === [])
                        <p class="preview-notice">Esta sección todavía no tiene parámetros activos.</p>
                    @elseif ($section['is_matrix'] && $section['status'] === \App\Models\OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS)
                        <p class="matrix-empty-status">Completada sin hallazgos.</p>
                    @elseif (($section['layout'] ?? 'standard') === 'paired' && ! $section['is_matrix'])
                        <table class="results paired-results">
                            <tbody>
                                @foreach (array_chunk($rows, 2) as $rowPair)
                                    <tr>
                                        @foreach ([0, 1] as $pairIndex)
                                            @php($row = $rowPair[$pairIndex] ?? null)
                                            @if ($row)
                                                <td class="parameter-name">{{ $row['name'] }}</td>
                                                <td class="result-value">
                                                    @if (! empty($row['flag']))
                                                        <strong class="out-of-range">{{ $row['result'] }}</strong>
                                                        <span class="chip chip-alert">{{ $flagLabels[$row['flag']] ?? '' }}</span>
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
                                @foreach ($rows as $row)
                                    <tr>
                                        <td style="width: 29%;">{{ $row['name'] }}</td>
                                        @foreach ($row['cells'] as $cell)
                                            <td style="width: {{ 71 / max(1, count($section['columns']) - 1) }}%;">{{ $cell }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @elseif ($isQualitative)
                        <table class="qualitative-cards">
                            <tbody>
                                @foreach (array_chunk($rows, 3) as $cardRow)
                                    <tr>
                                        @foreach ([0, 1, 2] as $cardIndex)
                                            @php($row = $cardRow[$cardIndex] ?? null)
                                            @if ($row)
                                                @php($isPositive = in_array(mb_strtolower(trim($row['result'])), $positiveValues, true))
                                                <td class="{{ $isPositive ? 'positive' : '' }}">
                                                    <span class="chip card-chip {{ $isPositive ? 'chip-alert' : 'chip-normal' }}">{{ $row['result'] }}</span>
                                                    {{ $row['name'] }}
                                                </td>
                                            @else
                                                <td class="empty-card"></td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <table class="results">
                            <thead>
                                <tr>
                                    <th style="width: {{ $columnWidths['name'] }}%;">Análisis{{ collect($rows)->contains(fn (array $row): bool => filled($row['reference'] ?? null)) ? ' · referencia' : '' }}</th>
                                    <th style="width: {{ $columnWidths['result'] }}%;">Resultado</th>
                                    @if ($showRange)
                                        <th style="width: {{ $columnWidths['range'] }}%;">Bajo · Normal · Alto</th>
                                        <th class="state-cell" style="width: {{ $columnWidths['state'] }}%;">Estado</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td>
                                            {{ $row['name'] }}
                                            @if (filled($row['reference'] ?? null))
                                                <span class="reference-line">{{ $row['reference'] }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if (! empty($row['flag']))
                                                <strong class="out-of-range result-value">{{ $row['result'] }}</strong>
                                            @else
                                                <span class="result-value">{{ $row['result'] }}</span>
                                            @endif
                                            @if ($showUnit && filled($row['unit']))
                                                <span class="unit">{{ $row['unit'] }}</span>
                                            @endif
                                        </td>
                                        @if ($showRange)
                                            <td>
                                                @if (! empty($row['indicator']))
                                                    <div class="range">
                                                        <div class="range-track"><div class="range-normal" style="margin-left: {{ $row['indicator']['zone_start'] }}%; width: {{ $row['indicator']['zone_width'] }}%;"></div></div>
                                                        <div class="range-marker-row" style="padding-left: {{ $row['indicator']['position'] }}%;"><img class="range-marker" src="{{ $rangeMarkers[! empty($row['flag']) ? 'alert' : 'normal'] }}" alt=""></div>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="state-cell">
                                                @if (! empty($row['flag']))
                                                    <span class="chip chip-alert">{{ $flagLabels[$row['flag']] ?? '' }}</span>
                                                @elseif (! empty($row['indicator']))
                                                    <span class="chip chip-normal">Normal</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </section>
            @endforeach
        @endforeach

        <p class="end-marker">— Fin del reporte —</p>
    </main>
</body>
</html>
