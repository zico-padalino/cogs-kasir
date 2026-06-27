<?php

namespace App\Http\Requests\Concerns;

use App\Support\Format;

trait NormalizesRupiahInput
{
    /** @param list<string> $fields */
    protected function normalizeRupiahFields(array $fields): void
    {
        $merged = [];

        foreach ($fields as $field) {
            if ($this->has($field)) {
                $merged[$field] = Format::parseRupiah($this->input($field));
            }
        }

        if ($merged !== []) {
            $this->merge($merged);
        }
    }

    /** @param list<string> $fields */
    protected function normalizeRupiahInArray(string $arrayKey, array $fields): void
    {
        $items = $this->input($arrayKey);

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            foreach ($fields as $field) {
                if (array_key_exists($field, $item)) {
                    $items[$index][$field] = Format::parseRupiah($item[$field]);
                }
            }
        }

        $this->merge([$arrayKey => $items]);
    }
}
