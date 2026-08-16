<?php

namespace App\Http\Controllers\Web\Kasir;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Support\KasirPin;
use App\Support\SessionPressure;
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

        return view('kasir.pin-unlock', [
            'shopName' => config('pos.shop_name'),
            'logoUrl' => ShopSettings::logoUrl(),
            'currentUser' => auth()->user(),
        ]);
    }

    public function status(): \Illuminate\Http\JsonResponse
    {
        $payload = array_merge(KasirPin::statusPayload(), [
            'redirect' => route('kasir.pin.unlock'),
            'ttl_minutes' => KasirPin::idleMinutes(),
        ]);
        SessionPressure::releaseEarly();

        return response()->json($payload);
    }

    /** Perpanjang sesi PIN karena ada aktivitas (sentuhan layar / input). */
    public function touch(): \Illuminate\Http\JsonResponse
    {
        if (! KasirPin::isUnlocked()) {
            $payload = array_merge(KasirPin::statusPayload(), [
                'redirect' => route('kasir.pin.unlock'),
                'ttl_minutes' => KasirPin::idleMinutes(),
            ]);
            SessionPressure::releaseEarly();

            return response()->json($payload, 423);
        }

        KasirPin::touch();
        $payload = array_merge(KasirPin::statusPayload(), [
            'redirect' => route('kasir.pin.unlock'),
            'ttl_minutes' => KasirPin::idleMinutes(),
        ]);
        SessionPressure::releaseEarly();

        return response()->json($payload);
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
            app(ActivityLogger::class)->record(
                'kasir',
                'pin_unlock_failed',
                'Percobaan buka PIN kasir gagal.',
                $request->user(),
            );

            throw ValidationException::withMessages([
                'pin' => 'PIN tidak dikenali. Coba lagi.',
            ]);
        }

        KasirPin::unlock($operator);

        app(ActivityLogger::class)->record(
            'kasir',
            'pin_unlock',
            'Kasir dibuka oleh '.$operator->name.'.',
            $request->user(),
            $operator,
            ['operator_name' => $operator->name, 'operator_id' => $operator->id],
            actorName: $operator->name,
        );

        $intended = $request->session()->pull('url.intended', route('kasir.index'));

        return redirect()
            ->to($intended)
            ->with('success', 'Kasir dibuka oleh '.$operator->name.'.');
    }

    public function lock(Request $request): RedirectResponse
    {
        $name = KasirPin::operatorName();
        KasirPin::lock();

        app(ActivityLogger::class)->record(
            'kasir',
            'pin_lock',
            'Sesi kasir dikunci'.($name ? ' ('.$name.')' : '').'.',
            $request->user(),
            properties: ['operator_name' => $name],
        );

        return redirect()
            ->route('kasir.pin.unlock')
            ->with('success', 'Sesi '.$name.' dikunci. Masukkan PIN untuk membuka lagi.');
    }
}
