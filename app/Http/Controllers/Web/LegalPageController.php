<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\ShopSettings;
use Illuminate\View\View;

class LegalPageController extends Controller
{
    public function terms(): View
    {
        return view('legal.terms', $this->shared());
    }

    public function privacy(): View
    {
        return view('legal.privacy', $this->shared());
    }

    /**
     * @return array{shopName: string, siteUrl: string, contactEmail: string}
     */
    private function shared(): array
    {
        return [
            'shopName' => (string) ShopSettings::get('shop_name', config('pos.shop_name', 'Kedai Tjoan')),
            'siteUrl' => url('/pesan'),
            'contactEmail' => 'admin@kedaitjoan.online',
        ];
    }
}
