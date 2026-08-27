<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class ConvertMenuImagesController extends Controller
{
    public function index(): View
    {
        return view('admin.convert-menu-images');
    }

    public function store(): RedirectResponse
    {
        $exitCode = Artisan::call('menu:convert-images-webp');
        $output = trim(Artisan::output());

        return redirect()
            ->route('admin.convert-menu-images')
            ->with($exitCode === 0 ? 'success' : 'error', $output ?: 'Proses konversi selesai.');
    }
}