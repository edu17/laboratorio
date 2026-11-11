<x-filament-panels::page>
    @php($orderExam = $this->getOrderExam())

    <div class="space-y-4">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p><strong>Orden:</strong> {{ $orderExam->order->number }}</p>
            <p><strong>Paciente:</strong> {{ $orderExam->order->patient->first_names }} {{ $orderExam->order->patient->last_names }}</p>
            <p><strong>Examen:</strong> {{ $orderExam->catalog_snapshot['exam']['name'] ?? $orderExam->exam->name }}</p>
            <p><strong>Estado:</strong> {{ $orderExam->status }}</p>
            <p><strong>Parámetros pendientes:</strong> {{ $this->getMissingResultCount() }}</p>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Los datos guardados aquí son un borrador. Aún requieren revisión y validación.</p>
        </div>

        <form wire:submit="save" class="space-y-6">
            {{ $this->form }}

            <div class="flex flex-wrap gap-3">
                <x-filament::button type="submit">Guardar borrador</x-filament::button>
                <x-filament::button color="gray" tag="a" :href="$this->getOrderViewUrl()">Volver a la orden</x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
