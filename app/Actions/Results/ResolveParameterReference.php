<?php

namespace App\Actions\Results;

/**
 * Resuelve la referencia de un parámetro según el sexo del paciente y marca los resultados numéricos fuera de rango.
 *
 * Claves admitidas en reference_config: min, max, min_femenino, max_femenino, min_masculino, max_masculino,
 * texto_femenino y texto_masculino. Las claves por sexo tienen prioridad sobre las generales.
 */
class ResolveParameterReference
{
    public const FLAG_LOW = 'bajo';

    public const FLAG_HIGH = 'alto';

    /** Número con comparador opcional: "1.02", "-3", "< 2.5", ">=10". */
    public const NUMBER_PATTERN = '/\A\s*(<=|>=|<|>)?\s*(-?\d+(?:\.\d+)?)\s*\z/';

    public const ALLOWED_CONFIG_KEYS = ['min', 'max', 'min_femenino', 'max_femenino', 'min_masculino', 'max_masculino', 'texto_femenino', 'texto_masculino'];

    /**
     * @param  array<string, mixed>  $parameter  Parámetro del snapshot del catálogo.
     * @return array{text: string, flag: string|null, indicator: array{zone_start: float, zone_width: float, position: float}|null}
     */
    public function handle(array $parameter, ?string $sex, mixed $value): array
    {
        $config = is_array($parameter['reference_config'] ?? null) ? $parameter['reference_config'] : [];
        $sex = in_array($sex, ['femenino', 'masculino'], true) ? $sex : null;
        $text = $this->configValue($config, 'texto', $sex)
            ?? $parameter['reference_text'] ?? '';
        $number = $this->plainNumber($parameter, $value);
        $min = $this->configValue($config, 'min', $sex);
        $max = $this->configValue($config, 'max', $sex);
        $min = is_numeric($min) ? (float) $min : null;
        $max = is_numeric($max) ? (float) $max : null;

        return [
            'text' => is_scalar($text) ? trim((string) $text) : '',
            'flag' => match (true) {
                $number === null => null,
                $min !== null && $number < $min => self::FLAG_LOW,
                $max !== null && $number > $max => self::FLAG_HIGH,
                default => null,
            },
            'indicator' => $number === null ? null : $this->indicator($number, $min, $max),
        ];
    }

    /**
     * Posición del resultado en una barra de 0 a 100: la zona normal ocupa el centro (25–75)
     * o se extiende hasta el borde cuando el rango solo tiene mínimo o solo máximo.
     *
     * @return array{zone_start: float, zone_width: float, position: float}|null
     */
    private function indicator(float $number, ?float $min, ?float $max): ?array
    {
        [$zoneStart, $zoneWidth, $position] = match (true) {
            $min !== null && $max !== null && $max > $min => [25.0, 50.0, 25 + ($number - $min) / ($max - $min) * 50],
            $min === null && $max !== null && $max > 0 => [0.0, 75.0, $number / $max * 75],
            $max === null && $min !== null && $min > 0 => [25.0, 75.0, $number < $min ? $number / $min * 25 : 25 + min(1, ($number - $min) / $min) * 75],
            default => [null, null, null],
        };

        if ($position === null) {
            return null;
        }

        return [
            'zone_start' => $zoneStart,
            'zone_width' => $zoneWidth,
            'position' => round(max(2, min(98, $position)), 1),
        ];
    }

    /**
     * Valor numérico sin comparador de un parámetro numérico; "< 5" no se puede ubicar ni marcar.
     *
     * @param  array<string, mixed>  $parameter
     */
    private function plainNumber(array $parameter, mixed $value): ?float
    {
        $type = $parameter['result_type'] ?? null;

        if ($type !== 'number' || ! is_scalar($value) || preg_match(self::NUMBER_PATTERN, (string) $value, $matches) !== 1 || $matches[1] !== '') {
            return null;
        }

        return (float) $matches[2];
    }

    /**
     * Normaliza un resultado numérico ("<2.5" → "< 2.5"); devuelve null si no es un número válido.
     */
    public static function normalizeNumber(string $value): ?string
    {
        if (preg_match(self::NUMBER_PATTERN, $value, $matches) !== 1) {
            return null;
        }

        return $matches[1] !== '' ? "{$matches[1]} {$matches[2]}" : $matches[2];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function configValue(array $config, string $key, ?string $sex): mixed
    {
        if ($sex !== null && filled($config["{$key}_{$sex}"] ?? null)) {
            return $config["{$key}_{$sex}"];
        }

        return filled($config[$key] ?? null) ? $config[$key] : null;
    }
}
