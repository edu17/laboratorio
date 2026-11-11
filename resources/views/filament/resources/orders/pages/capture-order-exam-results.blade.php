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

    <div class="grid gap-6">
        <x-filament::section compact>
            <div class="grid gap-4">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <strong class="text-base text-gray-950 dark:text-white">{{ $patient->full_name }}</strong>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ collect([$patient->age_display, ['femenino' => 'Femenino', 'masculino' => 'Masculino'][$patient->sex] ?? null, 'Orden '.$orderExam->order->number])->filter()->implode(' · ') }}
                    </span>
                </div>

                <div>
                    <div class="mb-1.5 flex justify-between gap-4 text-sm text-gray-700 tabular-nums dark:text-gray-300">
                        <span>{{ $captured }} de {{ $required }} resultados capturados</span>
                        <span @class(['font-semibold', 'text-success-700 dark:text-success-400' => $missing === 0])>
                            {{ $missing === 0 ? 'Examen completo' : ($missing === 1 ? 'Falta 1' : 'Faltan '.$missing) }}
                        </span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                        <div @class([
                            'h-full rounded-full transition-[width] duration-300',
                            'bg-primary-600 dark:bg-primary-400' => $missing > 0,
                            'bg-success-600 dark:bg-success-500' => $missing === 0,
                        ]) style="width: {{ $percent }}%"></div>
                    </div>
                </div>

                @if (count($orderExams) > 1)
                    <nav class="flex flex-wrap gap-2" aria-label="Exámenes de la orden">
                        @foreach ($orderExams as $item)
                            @php
                                $chipClasses = \Illuminate\Support\Arr::toCssClasses([
                                    'inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs',
                                    'border-primary-600 bg-primary-50 font-semibold text-primary-700 dark:border-primary-400 dark:bg-primary-500/15 dark:text-primary-200' => $item['is_current'],
                                    'border-gray-200 text-gray-700 dark:border-white/10 dark:text-gray-300' => ! $item['is_current'],
                                    'transition-colors hover:border-primary-400 hover:bg-primary-50 dark:hover:bg-primary-500/10' => $item['url'] !== null,
                                ]);
                                $countClasses = \Illuminate\Support\Arr::toCssClasses([
                                    'rounded-full px-2 py-0.5 text-[0.68rem] font-semibold tabular-nums',
                                    'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-300' => $item['missing'] === 0,
                                    'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300' => $item['missing'] > 0,
                                ]);
                                $countLabel = $item['missing'] === 0 ? 'Completo' : $item['missing'].' pend.';
                            @endphp

                            @if ($item['url'])
                                <a href="{{ $item['url'] }}" class="{{ $chipClasses }}">
                                    {{ $item['name'] }} <span class="{{ $countClasses }}">{{ $countLabel }}</span>
                                </a>
                            @else
                                <span class="{{ $chipClasses }}" @if ($item['is_current']) aria-current="page" @endif>
                                    {{ $item['name'] }} <span class="{{ $countClasses }}">{{ $countLabel }}</span>
                                </span>
                            @endif
                        @endforeach
                    </nav>
                @endif

                <p class="text-sm text-gray-500 dark:text-gray-400">Los datos guardados aquí son un borrador: aún requieren revisión y validación.</p>
            </div>
        </x-filament::section>

        <form wire:submit="save" class="grid gap-6">
            {{ $this->form }}

            <div class="flex flex-wrap items-center gap-3">
                @if ($nextOrderExam)
                    <x-filament::button type="button" wire:click="saveAndNext" icon="heroicon-o-arrow-right" icon-position="after">
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
