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
     * @return array{text: string, flag: string|null}
     */
    public function handle(array $parameter, ?string $sex, mixed $value): array
    {
        $config = is_array($parameter['reference_config'] ?? null) ? $parameter['reference_config'] : [];
        $sex = in_array($sex, ['femenino', 'masculino'], true) ? $sex : null;
        $text = $this->configValue($config, 'texto', $sex)
            ?? $parameter['reference'] ?? $parameter['reference_text'] ?? '';

        return [
            'text' => is_scalar($text) ? trim((string) $text) : '',
            'flag' => $this->flag($parameter, $config, $sex, $value),
        ];
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
     * @param  array<string, mixed>  $parameter
     * @param  array<string, mixed>  $config
     */
    private function flag(array $parameter, array $config, ?string $sex, mixed $value): ?string
    {
        $type = $parameter['type'] ?? $parameter['result_type'] ?? null;

        if ($type !== 'number' || ! is_scalar($value) || preg_match(self::NUMBER_PATTERN, (string) $value, $matches) !== 1 || $matches[1] !== '') {
            return null;
        }

        $number = (float) $matches[2];
        $min = $this->configValue($config, 'min', $sex);
        $max = $this->configValue($config, 'max', $sex);

        return match (true) {
            is_numeric($min) && $number < (float) $min => self::FLAG_LOW,
            is_numeric($max) && $number > (float) $max => self::FLAG_HIGH,
            default => null,
        };
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
