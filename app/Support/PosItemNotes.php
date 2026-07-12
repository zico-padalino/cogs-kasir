<?php

namespace App\Support;

class PosItemNotes
{
    /**
     * @return array{customer: string|null, addons: string|null, addon_labels: list<string>}
     */
    public static function split(?string $notes): array
    {
        $raw = trim((string) $notes);
        if ($raw === '') {
            return [
                'customer' => null,
                'addons' => null,
                'addon_labels' => [],
            ];
        }

        $segments = array_values(array_filter(array_map('trim', explode(' · ', $raw)), fn ($s) => $s !== ''));
        $customer = [];
        $addons = null;

        foreach ($segments as $segment) {
            if ($addons === null && str_starts_with($segment, '+')) {
                $addons = $segment;
                continue;
            }

            $customer[] = $segment;
        }

        $addonLabels = $addons
            ? array_values(array_filter(preg_split('/\s+(?=\+)/', $addons) ?: [], fn ($label) => $label !== ''))
            : [];

        $customerNote = trim(implode(' · ', $customer));

        return [
            'customer' => $customerNote !== '' ? $customerNote : null,
            'addons' => $addons,
            'addon_labels' => $addonLabels,
        ];
    }

    public static function merge(?string $customerNote, ?string $addonNote): ?string
    {
        $merged = trim(implode(' · ', array_filter([
            filled($customerNote) ? trim($customerNote) : null,
            filled($addonNote) ? trim($addonNote) : null,
        ])));

        if ($merged === '') {
            return null;
        }

        return mb_substr($merged, 0, 255);
    }

    public static function preserveAddons(?string $existingNotes, ?string $customerNote): ?string
    {
        $split = self::split($existingNotes);

        return self::merge($customerNote, $split['addons']);
    }
}
