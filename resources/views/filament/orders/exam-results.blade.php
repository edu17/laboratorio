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
    @forelse ($exam['sections'] as $section)
        <div>
            @if (count($exam['sections']) > 1 || $section['is_matrix'])
                <h4>{{ $section['name'] }}</h4>
            @endif

            @if ($section['is_matrix'])
                <p class="secondary mb-2">Estado de la matriz: {{ $matrixStatusLabels[$section['status']] ?? $section['status'] }}</p>

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
                        <colgroup><col class="w-[34%]"><col class="w-[18%]"><col class="w-[14%]"><col class="w-[34%]"></colgroup>
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
        <p class="secondary">Este examen no tiene parámetros solicitados.</p>
    @endforelse
</div>
