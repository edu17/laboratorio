@php
    /** @var array{code: string, name: string, sections: list<array<string, mixed>>} $exam */
    $flagLabels = [
        \App\Actions\Results\ResolveParameterReference::FLAG_HIGH => '▲ Alto',
        \App\Actions\Results\ResolveParameterReference::FLAG_LOW => '▼ Bajo',
    ];
    $matrixStatusLabels = [
        \App\Models\OrderExam::SECTION_STATUS_COMPLETED_WITH_FINDINGS => 'Completada con hallazgos',
        \App\Models\OrderExam::SECTION_STATUS_COMPLETED_WITHOUT_FINDINGS => 'Completada sin hallazgos',
        \App\Models\OrderExam::SECTION_STATUS_PENDING => 'Pendiente',
    ];
@endphp

<div class="lab-results">
    @once
        <style>
            .lab-results { display: grid; gap: 1rem; font-size: .875rem; }
            .lab-results h4 { margin: 0 0 .35rem; font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--gray-500); }
            .lab-results .table-wrap { overflow-x: auto; }
            .lab-results table.standard { table-layout: fixed; min-width: 36rem; }
            .lab-results table { width: 100%; border-collapse: collapse; }
            .lab-results th { text-align: left; font-weight: 500; font-size: .75rem; color: var(--gray-500); padding: .35rem .5rem; border-bottom: 1px solid var(--gray-200); white-space: nowrap; }
            .lab-results td { padding: .4rem .5rem; border-bottom: 1px solid var(--gray-100); vertical-align: top; color: var(--gray-950); }
            .lab-results tr:last-child td { border-bottom: 0; }
            .lab-results .result { font-weight: 600; }
            .lab-results .muted { color: var(--gray-400); font-style: italic; font-weight: 400; }
            .lab-results .secondary { color: var(--gray-500); }
            .lab-results .out-of-range { color: var(--danger-600); }
            .lab-results .chip { display: inline-block; margin-left: .35rem; padding: 0 .4rem; border-radius: .375rem; font-size: .7rem; font-weight: 600; background: var(--danger-50); color: var(--danger-700); white-space: nowrap; }
            .lab-results .empty { color: var(--gray-500); }
            .dark .lab-results th { border-color: var(--gray-700); color: var(--gray-400); }
            .dark .lab-results td { border-color: var(--gray-800); color: var(--gray-100); }
            .dark .lab-results .out-of-range { color: var(--danger-400); }
            .dark .lab-results .chip { background: color-mix(in oklab, var(--danger-500) 20%, transparent); color: var(--danger-300); }
        </style>
    @endonce

    @forelse ($exam['sections'] as $section)
        <div>
            @if (count($exam['sections']) > 1 || $section['is_matrix'])
                <h4>{{ $section['name'] }}</h4>
            @endif

            @if ($section['is_matrix'])
                <p class="secondary" style="margin: 0 0 .35rem">Estado de la matriz: {{ $matrixStatusLabels[$section['status']] ?? $section['status'] }}</p>

                @php($findingRows = array_filter($section['rows'], fn (array $row): bool => array_filter($row['cells'], fn (string $cell): bool => $cell !== '') !== []))

                @if ($findingRows !== [])
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    @foreach ($section['columns'] as $column)
                                        <th>{{ $column }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($findingRows as $row)
                                    <tr>
                                        <td>{{ $row['name'] }}</td>
                                        @foreach ($row['cells'] as $cell)
                                            <td>{{ $cell !== '' ? $cell : '—' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @else
                <div class="table-wrap">
                    <table class="standard">
                        <colgroup><col style="width: 34%"><col style="width: 18%"><col style="width: 14%"><col style="width: 34%"></colgroup>
                        <thead>
                            <tr>
                                <th>Parámetro</th>
                                <th>Resultado</th>
                                <th>Unidad</th>
                                <th>Referencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($section['rows'] as $row)
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td>
                                        @if ($row['result'] === '')
                                            <span class="muted">Pendiente</span>
                                        @else
                                            <span @class(['result', 'out-of-range' => ! empty($row['flag'])])>{{ $row['result'] }}</span>
                                            @if (! empty($row['flag']))
                                                <span class="chip">{{ $flagLabels[$row['flag']] ?? '' }}</span>
                                            @endif
                                        @endif
                                    </td>
                                    <td class="secondary">{{ $row['unit'] }}</td>
                                    <td class="secondary">{{ $row['reference'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @empty
        <p class="empty">Este examen no tiene parámetros solicitados.</p>
    @endforelse
</div>
