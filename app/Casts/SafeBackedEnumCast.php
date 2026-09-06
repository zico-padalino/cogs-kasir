<?php

namespace App\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ValueError;

/**
 * Cast enum yang tidak melempar 500 jika nilai DB kosong / tidak dikenal.
 *
 * @template TEnum of BackedEnum
 */
class SafeBackedEnumCast implements CastsAttributes
{
    /** @param  class-string<TEnum>  $enumClass */
    public function __construct(
        protected string $enumClass,
    ) {
        if (! is_subclass_of($this->enumClass, BackedEnum::class)) {
            throw new InvalidArgumentException($this->enumClass.' harus BackedEnum.');
        }
    }

    /** @return TEnum|null */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?BackedEnum
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof $this->enumClass) {
            return $value;
        }

        try {
            return ($this->enumClass)::tryFrom($value);
        } catch (ValueError|InvalidArgumentException) {
            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        $enum = ($this->enumClass)::tryFrom($value);

        return $enum?->value ?? $value;
    }
}
