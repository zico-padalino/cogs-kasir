<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\ResetDataService;
use Illuminate\Http\Request;

class ResetDataController extends Controller
{
    public function show(ResetDataService $resetService)
    {
        return view('reset.show', [
            'counts' => $resetService->counts(),
        ]);
    }

    public function reset(Request $request, ResetDataService $resetService)
    {
        $request->validate([
            'confirmation' => ['required', 'in:RESET'],
        ]);

        $resetService->resetAll();

        return redirect()->route('dashboard')->with('success', 'Semua data berhasil dihapus. Database sekarang kosong.');
    }
}
