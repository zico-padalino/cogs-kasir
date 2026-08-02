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
     * @return array{
     *     shopName: string,
     *     siteUrl: string,
     *     contactName: string,
     *     contactWhatsapp: string,
     *     whatsappUrl: string
     * }
     */
    private function shared(): array
    {
        $whatsapp = '085161852230';

        return [
            'shopName' => (string) ShopSettings::get('shop_name', config('pos.shop_name', 'Kedai Tjoan')),
            'siteUrl' => url('/pesan'),
            'contactName' => 'Zico Padalino',
            'contactWhatsapp' => $whatsapp,
            'whatsappUrl' => 'https://wa.me/6285161852230',
        ];
    }
}
