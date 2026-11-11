<x-filament-panels::page>
    @php
        $orderExam = $this->getOrderExam();
        $patient = $orderExam->order->patient;
        $missing = $this->getMissingResultCount();
        $required = $orderExam->requiredResultCount();
        $captured = max(0, $required - $missing);
        $percent = $required > 0 ? (int) round($captured * 100 / $required) : 0;
        $orderExams = $this->getOrderExamsProgress();
        $nextOrderExam = $this->getNextOrderExam();
    @endphp

    <style>
        .capture-summary { display: grid; gap: .9rem; }
        .capture-summary .patient { display: flex; flex-wrap: wrap; align-items: baseline; gap: .25rem .75rem; }
        .capture-summary .patient strong { font-size: 1rem; color: var(--gray-950); }
        .capture-summary .secondary { color: var(--gray-500); font-size: .875rem; }
        .capture-summary .progress-label { display: flex; justify-content: space-between; gap: 1rem; font-size: .875rem; color: var(--gray-700); margin-bottom: .35rem; }
        .capture-summary .progress-track { height: .5rem; border-radius: 9999px; background: var(--gray-100); overflow: hidden; }
        .capture-summary .progress-fill { height: 100%; border-radius: 9999px; background: var(--primary-600); transition: width .3s; }
        .capture-summary .progress-fill.complete { background: var(--success-600); }
        .capture-summary .exams { display: flex; flex-wrap: wrap; gap: .5rem; }
        .capture-summary .exam-chip { display: inline-flex; align-items: center; gap: .4rem; padding: .3rem .7rem; border-radius: .5rem; border: 1px solid var(--gray-200); font-size: .8rem; color: var(--gray-700); text-decoration: none; }
        .capture-summary a.exam-chip:hover { border-color: var(--primary-400); background: var(--primary-50); }
        .capture-summary .exam-chip.current { border-color: var(--primary-600); background: var(--primary-50); color: var(--primary-700); font-weight: 600; }
        .capture-summary .exam-chip .count { font-size: .7rem; padding: 0 .35rem; border-radius: .375rem; background: var(--warning-50); color: var(--warning-700); }
        .capture-summary .exam-chip .count.done { background: var(--success-50); color: var(--success-700); }
        .capture-actions { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; }
        .dark .capture-summary .patient strong { color: var(--gray-50); }
        .dark .capture-summary .progress-label { color: var(--gray-300); }
        .dark .capture-summary .progress-track { background: var(--gray-800); }
        .dark .capture-summary .exam-chip { border-color: var(--gray-700); color: var(--gray-300); }
        .dark .capture-summary .exam-chip.current, .dark .capture-summary a.exam-chip:hover { background: color-mix(in oklab, var(--primary-500) 20%, transparent); color: var(--primary-200); }
    </style>

    <div class="capture-summary">
        <x-filament::section compact>
            <div class="capture-summary">
                <div class="patient">
                    <strong>{{ $patient->full_name }}</strong>
                    <span class="secondary">
                        {{ collect([$patient->age_display, ['femenino' => 'Femenino', 'masculino' => 'Masculino'][$patient->sex] ?? null, 'Orden '.$orderExam->order->number])->filter()->implode(' · ') }}
                    </span>
                </div>

                <div>
                    <div class="progress-label">
                        <span>{{ $captured }} de {{ $required }} resultados capturados</span>
                        <span>{{ $missing === 0 ? 'Examen completo' : ($missing === 1 ? 'Falta 1' : 'Faltan '.$missing) }}</span>
                    </div>
                    <div class="progress-track" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                        <div @class(['progress-fill', 'complete' => $missing === 0]) style="width: {{ $percent }}%"></div>
                    </div>
                </div>

                @if (count($orderExams) > 1)
                    <div class="exams" aria-label="Exámenes de la orden">
                        @foreach ($orderExams as $item)
                            @php($countLabel = $item['missing'] === 0 ? 'Completo' : $item['missing'].' pend.')
                            @if ($item['url'])
                                <a href="{{ $item['url'] }}" class="exam-chip">
                                    {{ $item['name'] }} <span @class(['count', 'done' => $item['missing'] === 0])>{{ $countLabel }}</span>
                                </a>
                            @else
                                <span @class(['exam-chip', 'current' => $item['is_current']]) @if ($item['is_current']) aria-current="page" @endif>
                                    {{ $item['name'] }} <span @class(['count', 'done' => $item['missing'] === 0])>{{ $countLabel }}</span>
                                </span>
                            @endif
                        @endforeach
                    </div>
                @endif

                <p class="secondary" style="margin: 0">Los datos guardados aquí son un borrador: aún requieren revisión y validación.</p>
            </div>
        </x-filament::section>

        <form wire:submit="save" class="capture-summary" style="gap: 1.5rem">
            {{ $this->form }}

            <div class="capture-actions">
                @if ($nextOrderExam)
                    <x-filament::button type="button" wire:click="saveAndNext" icon="heroicon-o-arrow-right">
                        Guardar y siguiente examen
                    </x-filament::button>
                    <x-filament::button type="submit" color="gray">Guardar borrador</x-filament::button>
                @else
                    <x-filament::button type="submit">Guardar borrador</x-filament::button>
                    <x-filament::button type="button" color="gray" wire:click="saveAndNext">Guardar y volver a la orden</x-filament::button>
                @endif
                <x-filament::button color="gray" tag="a" :href="$this->getOrderViewUrl()" outlined>Volver sin guardar</x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
