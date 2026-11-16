<?php

namespace App\Support;

use App\Models\Quote;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class QuotePdf
{
    public static function download(Quote $quote): Response
    {
        $quote->loadMissing('client.users', 'items', 'acceptance');

        return Pdf::loadView('pdf.quote', ['quote' => $quote, 'settings' => Setting::values()])
            ->setPaper('a4')
            ->download('Quote-'.str_replace(' ', '-', $quote->reference()).'.pdf');
    }
}
