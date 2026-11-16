<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class DocumentPdf
{
    public static function invoice(Invoice $invoice): Response
    {
        $invoice->loadMissing('client.users', 'items', 'quote');

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice, 'settings' => Setting::values()])
            ->setPaper('a4')
            ->download(($invoice->number ?? 'Draft-invoice').'.pdf');
    }
}
