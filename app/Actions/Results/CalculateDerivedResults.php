<?php

namespace App\Actions\Results;

/**
 * Calcula resultados derivados de otros parámetros del mismo examen.
 *
 * Un valor calculado se guarda con "calculated" => true. Se recalcula mientras el campo esté vacío
 * o conserve el último valor calculado; si alguien escribe otro valor, se respeta como corrección manual.
 */
class CalculateDerivedResults
{
    /** Por encima de este valor de triglicéridos la fórmula de Friedewald no es válida. */
    public const FRIEDEWALD_MAX_TRIGLYCERIDES = 400;

    /**
     * Se guarda cuando hay datos pero la fórmula no aplica (triglicéridos > 400, resultado negativo,
     * dato con comparador): permite validar la orden y se imprime tal cual en el reporte.
     */
    public const NOT_CALCULABLE = 'No calculable';

    public const NUMBER_OR_NOT_CALCULABLE_PATTERN = '/\A\s*(?:(?:<=|>=|<|>)?\s*-?\d+(?:\.\d+)?|No calculable)\s*\z/';

    /**
     * @var array<string, array{inputs: list<string>, description: string}>
     */
    public const FORMULAS = [
        'bilirrubina_indirecta' => [
            'inputs' => ['bilirrubina_total', 'bilirrubina_directa'],
            'description' => 'Bilirrubina total − bilirrubina directa',
        ],
        'colesterol_vldl' => [
            'inputs' => ['trigliceridos'],
            'description' => 'Triglicéridos ÷ 5 (no se calcula con triglicéridos mayores de 400 mg/dL)',
        ],
        'colesterol_ldl' => [
            'inputs' => ['colesterol_total', 'colesterol_hdl', 'trigliceridos'],
            'description' => 'Colesterol total − HDL − triglicéridos ÷ 5 (Friedewald; no se calcula con triglicéridos mayores de 400 mg/dL)',
        ],
        'psa_relacion_libre_total' => [
            'inputs' => ['psa_libre', 'psa_total'],
            'description' => 'PSA libre ÷ PSA total × 100',
        ],
    ];

    /**
     * @param  array<string, array<string, mixed>>  $values  Resultados normalizados que se van a guardar, por código.
     * @param  array<string, mixed>  $storedValues  Resultados guardados antes de este cambio, por código.
     * @return array<string, array<string, mixed>>
     */
    public function handle(array $values, array $storedValues): array
    {
        foreach (self::FORMULAS as $target => $formula) {
            if (! array_key_exists($target, $values)) {
                continue;
            }

            $submitted = $values[$target]['value'] ?? null;
            $previous = is_array($storedValues[$target] ?? null) ? $storedValues[$target] : [];
            $keepsCalculatedValue = ($previous['calculated'] ?? false) === true && ($previous['value'] ?? null) === $submitted;

            if (filled($submitted) && ! $keepsCalculatedValue) {
                continue;
            }

            $rawInputs = array_map(fn (string $input): mixed => $values[$input]['value'] ?? null, $formula['inputs']);

            if (collect($rawInputs)->contains(fn (mixed $input): bool => blank($input))) {
                $values[$target] = ['value' => null];

                continue;
            }

            $inputs = array_combine($formula['inputs'], array_map($this->plainNumber(...), $rawInputs));
            $result = in_array(null, $inputs, true) ? null : $this->calculate($target, $inputs);

            $values[$target] = [
                'value' => $result === null ? self::NOT_CALCULABLE : $this->format($result),
                'calculated' => true,
            ];
        }

        return $values;
    }

    /**
     * Un parámetro calculado acepta, además de un número, el texto "No calculable".
     */
    public static function acceptsNotCalculable(string $parameterCode): bool
    {
        return isset(self::FORMULAS[$parameterCode]);
    }

    /**
     * @param  array<string, float>  $inputs
     */
    private function calculate(string $target, array $inputs): ?float
    {
        $triglyceridesAllowed = ($inputs['trigliceridos'] ?? 0) <= self::FRIEDEWALD_MAX_TRIGLYCERIDES;

        $result = match ($target) {
            'bilirrubina_indirecta' => $inputs['bilirrubina_total'] - $inputs['bilirrubina_directa'],
            'colesterol_vldl' => $triglyceridesAllowed ? $inputs['trigliceridos'] / 5 : null,
            'colesterol_ldl' => $triglyceridesAllowed
                ? $inputs['colesterol_total'] - $inputs['colesterol_hdl'] - ($inputs['trigliceridos'] / 5)
                : null,
            'psa_relacion_libre_total' => $inputs['psa_total'] > 0 ? $inputs['psa_libre'] / $inputs['psa_total'] * 100 : null,
            default => null,
        };

        return $result !== null && $result >= 0 ? $result : null;
    }

    /**
     * Solo números sin comparador: "< 5" no sirve para calcular.
     */
    private function plainNumber(mixed $value): ?float
    {
        if (! is_string($value) || preg_match(ResolveParameterReference::NUMBER_PATTERN, $value, $matches) !== 1 || $matches[1] !== '') {
            return null;
        }

        return (float) $matches[2];
    }

    private function format(float $value): string
    {
        $formatted = rtrim(rtrim(number_format(round($value, 2), 2, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
