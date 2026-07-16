<?php

namespace App\Http\Controllers\Web\Kasir;

use App\Http\Controllers\Controller;
use App\Support\KasirPin;
use App\Support\ShopSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KasirPinController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (KasirPin::isUnlocked()) {
            return redirect()->route('kasir.index');
        }

        $ownEmployee = KasirPin::employeeForUser(auth()->user());

        return view('kasir.pin-unlock', [
            'shopName' => config('pos.shop_name'),
            'logoUrl' => ShopSettings::logoUrl(),
            'currentUser' => auth()->user(),
            'hasOwnPin' => $ownEmployee ? KasirPin::hasPin($ownEmployee) : false,
        ]);
    }

    public function status(): \Illuminate\Http\JsonResponse
    {
        return response()->json(array_merge(KasirPin::statusPayload(), [
            'redirect' => route('kasir.pin.unlock'),
            'ttl_minutes' => KasirPin::idleMinutes(),
        ]));
    }

    public function unlock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'digits_between:4,6'],
        ], [
            'pin.required' => 'PIN wajib diisi.',
            'pin.digits_between' => 'PIN harus 4–6 digit.',
        ]);

        $operator = KasirPin::findByPin($validated['pin']);

        if (! $operator) {
            throw ValidationException::withMessages([
                'pin' => 'PIN tidak dikenali. Coba lagi.',
            ]);
        }

        KasirPin::unlock($operator);

        $intended = $request->session()->pull('url.intended', route('kasir.index'));

        return redirect()
            ->to($intended)
            ->with('success', 'Kasir dibuka oleh '.$operator->name.'. Sesi PIN '.KasirPin::idleMinutes().' menit.');
    }

    public function lock(Request $request): RedirectResponse
    {
        $name = KasirPin::operatorName();
        KasirPin::lock();

        return redirect()
            ->route('kasir.pin.unlock')
            ->with('success', 'Sesi '.$name.' dikunci. Masukkan PIN untuk membuka lagi.');
    }
}
