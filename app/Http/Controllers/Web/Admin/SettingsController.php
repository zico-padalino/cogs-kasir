<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Support\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', [
            'settings' => ShopSettings::all(),
            'logoUrl' => ShopSettings::logoUrl(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop_name' => ['required', 'string', 'max:80'],
            'shop_title' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ], [
            'shop_name.required' => 'Nama toko wajib diisi.',
            'logo.image' => 'Logo harus berupa gambar.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
        ]);

        $payload = [
            'shop_name' => trim($validated['shop_name']),
            'shop_title' => trim((string) ($validated['shop_title'] ?? '')),
        ];

        $currentLogo = ShopSettings::get('logo_path');

        if ($request->boolean('remove_logo') && $currentLogo) {
            Storage::disk('public')->delete($currentLogo);
            $payload['logo_path'] = null;
        } elseif ($request->hasFile('logo')) {
            if ($currentLogo) {
                Storage::disk('public')->delete($currentLogo);
            }
            $payload['logo_path'] = $request->file('logo')->store('branding', 'public');
        }

        ShopSettings::put($payload);

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Pengaturan toko berhasil disimpan.');
    }
}
