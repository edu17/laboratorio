<?php

namespace App\Models;

use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable(['first_names', 'last_names', 'birth_date', 'sex', 'phone', 'identifier'])]
class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use HasFactory;

    /** Partículas que se escriben en minúscula dentro de un nombre: "María de los Ángeles". */
    private const NAME_PARTICLES = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'van', 'von'];

    protected static function booted(): void
    {
        static::saving(function (Patient $patient): void {
            $patient->search_name = self::comparableName($patient->full_name);
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function latestOrder(): HasOne
    {
        return $this->hasOne(Order::class)->latestOfMany('ordered_at');
    }

    /**
     * Mismo formato para todos los registros, escriba como se escriba: espacios limpios y mayúscula inicial,
     * con las partículas en minúscula. Así un paciente no aparece dos veces por una diferencia de escritura.
     */
    public static function normalizeName(?string $name): ?string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $name));

        if ($name === '') {
            return null;
        }

        $words = explode(' ', mb_convert_case(mb_strtolower($name), MB_CASE_TITLE));

        return collect($words)
            ->map(fn (string $word, int $index): string => $index > 0 && in_array(mb_strtolower($word), self::NAME_PARTICLES, true) ? mb_strtolower($word) : $word)
            ->implode(' ');
    }

    /**
     * Clave para comparar nombres sin importar mayúsculas, tildes ni espacios.
     */
    public static function comparableName(?string $name): string
    {
        return Str::of((string) $name)->ascii()->lower()->squish()->toString();
    }

    /**
     * Cada palabra buscada debe aparecer en nombres, apellidos, documento o teléfono: "rosario esquivel" encuentra
     * a Rosario Nerea Esquivel Valladares.
     */
    public function scopeMatchingSearch(Builder $query, string $search): Builder
    {
        foreach (preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
            $digits = preg_replace('/\D+/', '', $term);

            $query->where(fn (Builder $query): Builder => $query
                ->where('search_name', 'like', '%'.self::comparableName($term).'%')
                ->orWhere('identifier', 'like', '%'.mb_strtoupper($term).'%')
                ->when($digits !== '', fn (Builder $query): Builder => $query->orWhere('phone', 'like', '%'.$digits.'%')));
        }

        return $query;
    }

    /**
     * Pacientes ya registrados que podrían ser la misma persona: mismo nombre completo (sin importar tildes
     * ni mayúsculas), mismo documento, o mismos apellidos y fecha de nacimiento.
     *
     * @return Collection<int, Patient>
     */
    public static function possibleDuplicates(?string $firstNames, ?string $lastNames, mixed $birthDate = null, ?string $identifier = null, ?int $ignoreId = null): Collection
    {
        $fullName = self::comparableName($firstNames.' '.$lastNames);
        $comparableLastNames = self::comparableName($lastNames);
        $birthDate = filled($birthDate) ? Carbon::parse($birthDate)->toDateString() : null;
        $identifier = self::normalizeIdentifier($identifier);
        $firstSurname = Str::of(self::comparableName($lastNames))->before(' ')->toString();

        if ($fullName === '' && $identifier === null) {
            return new Collection;
        }

        return self::query()
            ->when($ignoreId, fn (Builder $query, int $id): Builder => $query->whereKeyNot($id))
            ->where(fn (Builder $query): Builder => $query
                ->when($firstSurname !== '', fn (Builder $query): Builder => $query->where('search_name', 'like', '%'.$firstSurname.'%'))
                ->when($identifier, fn (Builder $query, string $identifier): Builder => $query->orWhere('identifier', $identifier)))
            ->limit(50)
            ->get()
            ->filter(fn (Patient $patient): bool => ($identifier !== null && $patient->identifier === $identifier)
                || self::comparableName($patient->full_name) === $fullName
                || ($birthDate !== null && $comparableLastNames !== '' && $patient->birth_date?->toDateString() === $birthDate
                    && self::comparableName($patient->last_names) === $comparableLastNames))
            ->values();
    }

    public static function normalizeIdentifier(?string $identifier): ?string
    {
        $identifier = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', (string) $identifier)));

        return $identifier === '' ? null : $identifier;
    }

    protected function firstNames(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => self::normalizeName($value));
    }

    protected function lastNames(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => self::normalizeName($value));
    }

    protected function identifier(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => self::normalizeIdentifier($value));
    }

    protected function phone(): Attribute
    {
        return Attribute::set(function (?string $value): ?string {
            $digits = preg_replace('/\D+/', '', (string) $value);

            return $digits === '' ? null : $digits;
        });
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_names.' '.$this->last_names));
    }

    /**
     * Edad legible: años, o meses/días en recién nacidos.
     */
    protected function ageDisplay(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->birth_date) {
                return null;
            }

            $years = (int) $this->birth_date->diffInYears(now());
            $months = (int) $this->birth_date->diffInMonths(now());

            return match (true) {
                $years > 0 => $years.($years === 1 ? ' año' : ' años'),
                $months > 0 => $months.($months === 1 ? ' mes' : ' meses'),
                default => max(0, (int) $this->birth_date->diffInDays(now())).' días',
            };
        });
    }

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }
}
