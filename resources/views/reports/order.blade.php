<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte {{ $report['order']['number'] }}</title>
    <style>
        @page { size: letter portrait; margin: 15mm 15mm {{ 15 + ($footerReserveMm ?? 50) }}mm 15mm; }

        body {
            color: #17212b;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            line-height: 1.3;
        }

        p { margin: 0; }
        table { border-collapse: collapse; }

        .report-header { margin-bottom: 2.5mm; }
        .header-layout { width: 100%; }
        .header-layout td { vertical-align: middle; }
        .institution-cell { width: 78%; text-align: center; }
        .qr-cell { width: 22%; text-align: right; }
        .institution-name {
            margin: 0;
            color: #123b42;
            font-size: 13pt;
            font-weight: bold;
            line-height: 1.15;
        }
        .institution-detail { margin-top: 0.6mm; color: #52616b; font-size: 6.8pt; }
        .qr-corner { display: inline-block; width: 25mm; text-align: center; }
        .qr-corner img { display: block; width: 21mm; height: 21mm; margin: 0 auto; }
        .qr-caption { margin-top: 0.5mm; color: #52616b; font-size: 6pt; }

        .details { width: 100%; margin: 0 0 4mm; border-top: 2px solid #17212b; border-bottom: 1px solid #a8b2b9; }
        .details td { padding: 1.2mm 2mm; vertical-align: top; }
        .details .field-label { width: 24%; color: #52616b; font-size: 8pt; }
        .details .field-value { width: 26%; font-size: 9pt; }
        .details .patient-name { font-weight: bold; font-size: 10pt; }
        .details tr + tr td { border-top: 1px solid #d8dee2; }
        .label { color: #61717b; font-size: 7pt; text-transform: uppercase; }
        .value { color: #17212b; font-size: 9pt; }
        .validated-state {
            display: inline-block;
            padding: 0.8mm 2mm;
            color: #174c45;
            background-color: #e6f1ee;
            border: 1px solid #aac9c1;
            font-size: 8pt;
            font-weight: bold;
        }

        .exam-title {
            margin: 0 0 3mm;
            padding: 1.8mm 3mm;
            background-color: #e9edef;
            border-left: 3px solid #123b42;
        }
        .exam-title h2 { margin: 0; color: #123b42; font-size: 12pt; text-align: left; }
        .section { margin-bottom: 3mm; page-break-inside: avoid; }
        .section-long { page-break-inside: auto; }
        .section-title {
            margin: 0 0 1.5mm;
            padding: 1.2mm 2mm;
            color: #263943;
            background-color: #f1f3f4;
            border-bottom: 1px solid #c5cdd2;
            border-left: 2px solid #527d79;
            font-size: 9pt;
            font-weight: bold;
        }

        .results { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .results th, .results td { padding: 1.3mm 1.8mm; border: 1px solid #263943; vertical-align: top; }
        .results th { color: #fff; background-color: #123b42; font-weight: bold; text-align: left; }
        .results thead { display: table-header-group; }
        .results tr { page-break-inside: avoid; }
        .results .parameter { width: 32%; }
        .results .result { width: 25%; }
        .results .unit { width: 14%; }
        .results .reference { width: 29%; }
        .results:not(.matrix) tbody td:first-child { background-color: #f1f4f5; font-weight: bold; }

        .paired-results { border: 0; }
        .paired-results td { padding: 1.7mm 1.6mm; border: 0; border-bottom: 1px solid #d6dde1; }
        .paired-results .parameter-name { width: 25%; color: #465862; background-color: #f1f4f5; font-weight: bold; }
        .paired-results .result-value { width: 25%; color: #17212b; font-size: 9.5pt; font-weight: bold; }
        .paired-results td:nth-child(2) { border-right: 1px solid #d6dde1; }
        .paired-results .result-detail { display: block; margin-top: 0.5mm; color: #61717b; font-size: 7pt; font-weight: normal; }
        .paired-results .empty-pair { border-bottom-color: transparent; }

        .matrix th:not(:first-child), .matrix td:not(:first-child) { text-align: center; }
        .matrix td:first-child { text-align: left; font-weight: bold; }
        .matrix td { font-size: 8pt; }
        .matrix-empty-status { padding: 2mm 2.5mm; color: #174c45; background-color: #e6f1ee; border: 1px solid #aac9c1; font-size: 8pt; }

        .validation {
            margin-top: 5mm;
            padding-top: 2mm;
            border-top: 1px solid #829099;
            color: #34434c;
            font-size: 7.5pt;
        }
        .report-assets { width: 100%; margin: 0 0 4mm; table-layout: fixed; }
        .report-assets td { vertical-align: bottom; text-align: center; }
        .signature-cell { width: 42%; padding: 0 2mm; }
        .seal-cell { width: 16%; }
        .signature-image { display: block; max-width: 48mm; max-height: 15mm; margin: 0 auto 1mm; }
        .seal-image { display: block; max-width: 18mm; max-height: 18mm; margin: 0 auto; }
        .signature-label { padding-top: 1mm; border-top: 1px solid #829099; color: #34434c; font-size: 7pt; }
        .seal-label { margin-top: 1mm; color: #61717b; font-size: 6.5pt; }
        .validation .version { margin-top: 1mm; color: #71808a; font-size: 6.5pt; }
        .validation { page-break-inside: avoid; }
        .footer-logo { display: block; max-width: 23mm; max-height: 16mm; margin: 0 auto 1mm; }
        .contact-footer { width: 100%; margin-top: 3mm; border-top: 2px solid #123b42; }
        .contact-footer td { padding: 2mm 0; vertical-align: top; font-size: 7.5pt; }
        .contact-footer .emergency { width: 30%; text-align: right; }
        .validation-metadata { width: 100%; }
        .validation-metadata td { width: 50%; vertical-align: top; }
        .validation-metadata td:last-child { text-align: right; }
        .muted { color: #61717b; }
        .report-header { page-break-inside: avoid; }
        .report-body { display: block; }
        .preview-notice { padding: 1.5mm 2mm; margin-bottom: 2mm; border: 1px solid #a16207; color: #713f12; background-color: #fffbeb; font-size: 8pt; }
        .exam-title { page-break-after: avoid; }
        .report-footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -{{ $footerReserveMm ?? 50 }}mm;
            margin-top: 0;
            border-top: 2px solid #123b42;
        }

        .compact-report { margin: 0; line-height: 1.15; }
        .compact-report .report-header { margin-bottom: 1.5mm; }
        .compact-report .details { margin-bottom: 2mm; }
        .compact-report .details td { padding: 0.6mm 1.5mm; }
        .compact-report .exam-title { margin-bottom: 1.5mm; padding: 1.2mm 2mm; }
        .compact-report .exam-title h2 { font-size: 11pt; }
        .compact-report .section { margin-bottom: 1mm; }
        .compact-report .section-title { margin-bottom: 0.8mm; padding: 0.8mm 1.5mm; }
        .compact-report .results th, .compact-report .results td { padding: 0.7mm 1.5mm; }
        .compact-report .paired-results td { padding-top: 0.4mm; padding-bottom: 0.4mm; }
        .compact-report .matrix th, .compact-report .matrix td { padding: 0.35mm 1.2mm; font-size: 8pt; line-height: 1.1; }
        .compact-report .paired-results .result-value { font-size: 9pt; }
        .compact-report .validation { margin-top: 0; padding-top: 1mm; }
        .compact-report .report-assets { margin-bottom: 1mm; }
        .compact-report .signature-image { max-height: 9mm; max-width: 40mm; }
        .compact-report .footer-logo, .compact-report .seal-image {
            display: inline-block;
            max-width: 12mm;
            max-height: 12mm;
            margin: 0 0.3mm;
            vertical-align: middle;
        }
        .compact-report .contact-footer { margin-top: 1.5mm; }
        .compact-report .contact-footer td { padding: 1.2mm 0; }
    </style>
</head>
<body class="{{ count($report['exams']) === 1 && ($report['exams'][0]['code'] ?? '') === 'HECES' ? 'compact-report' : '' }}">
    @include('reports.footer', ['report' => $report])
    <header class="report-header">
        <table class="header-layout">
            <tbody>
                <tr>
                    <td class="institution-cell">
                        <h1 class="institution-name">{{ $report['institution']['name'] }}</h1>
                        @if (filled($report['institution']['subtitle']))
                            <p class="institution-detail">{{ $report['institution']['subtitle'] }}</p>
                        @endif
                    </td>
                    <td class="qr-cell">
                        @if (isset($report['public_access']['qr_code']))
                            <div class="qr-corner">
                                <img src="{{ $report['public_access']['qr_code'] }}" alt="QR para consultar el reporte">
                                <p class="qr-caption">Escanea para consultar</p>
                            </div>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>

    <table class="details">
        <tbody>
            <tr>
                <td class="field-label">Hora/Fecha informe</td>
                <td class="field-value" colspan="3">{{ $report['validation']['validated_at_display'] }}</td>
            </tr>
            <tr>
                <td class="field-label">R. Sanitario -DRACES-</td>
                <td class="field-value">{{ $report['institution']['sanitary_registration'] ?: 'Sin registrar' }}</td>
                <td class="field-label">Orden No.</td>
                <td class="field-value"><strong>{{ $report['order']['number'] }}</strong></td>
            </tr>
            <tr>
                <td class="field-label">Paciente</td>
                <td class="field-value patient-name" colspan="3">{{ trim(($report['patient']['first_names'] ?? '').' '.($report['patient']['last_names'] ?? '')) }}</td>
            </tr>
            <tr>
                <td class="field-label">Género</td>
                <td class="field-value">{{ $report['patient']['gender_display'] }}</td>
                <td class="field-label">Edad</td>
                <td class="field-value">{{ $report['patient']['age_display'] }}</td>
            </tr>
            <tr>
                <td class="field-label">Doctor(a)</td>
                <td class="field-value" colspan="3">{{ $report['order']['referring_doctor'] ?? 'Sin registrar' }}</td>
            </tr>
        </tbody>
    </table>

    </header>
    <main class="report-body">
    @if ($report['is_preview'] ?? false)
        <p class="preview-notice">BORRADOR — Datos ficticios. No es un resultado clínico. Se muestran las secciones y parámetros activos guardados.</p>
        @foreach ($report['preview_notes'] ?? [] as $note)
            <p class="preview-notice">{{ $note }}</p>
        @endforeach
    @endif
    @foreach ($report['exams'] as $exam)
        <header class="exam-title">
            <h2>{{ $exam['name'] }}</h2>
        </header>

        @if (($report['is_preview'] ?? false) && $exam['sections'] === [])
            <p class="preview-notice">Este examen todavía no tiene secciones activas configuradas.</p>
        @endif

        @foreach ($exam['sections'] as $section)
            <section class="section {{ count($section['rows']) > 20 ? 'section-long' : '' }}">
                <h3 class="section-title">{{ $section['name'] }}</h3>
                @if (($report['is_preview'] ?? false) && $section['rows'] === [])
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
                                                {{ $row['result'] }}
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
                @else
                    <table class="results {{ $section['is_matrix'] ? 'matrix' : '' }}">
                        @if ($section['is_matrix'])
                            <colgroup>
                                <col style="width: 29%;">
                                @foreach (array_slice($section['columns'], 1) as $column)
                                    <col style="width: {{ 71 / max(1, count($section['columns']) - 1) }}%;">
                                @endforeach
                            </colgroup>
                        @endif
                        <thead>
                            <tr>
                                @if ($section['is_matrix'])
                                    @foreach ($section['columns'] as $column)
                                        <th style="width: {{ $loop->first ? 29 : 71 / max(1, count($section['columns']) - 1) }}%;">{{ $column }}</th>
                                    @endforeach
                                @else
                                    <th class="parameter">Parámetro</th>
                                    <th class="result">Resultado</th>
                                    <th class="unit">Unidad</th>
                                    <th class="reference">Referencia</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($section['rows'] as $row)
                                <tr>
                                    @if ($section['is_matrix'])
                                        <td style="width: 29%;">{{ $row['name'] }}</td>
                                        @foreach ($row['cells'] as $cell)
                                            <td style="width: {{ 71 / max(1, count($section['columns']) - 1) }}%;">{{ $cell }}</td>
                                        @endforeach
                                    @else
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $row['result'] }}</td>
                                        <td>{{ $row['unit'] }}</td>
                                        <td>{{ $row['reference'] }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        @endforeach
    @endforeach
    </main>


</body>
</html>
