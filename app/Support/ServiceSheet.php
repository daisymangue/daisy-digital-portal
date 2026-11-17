<?php

namespace App\Support;

use App\Models\Service;
use App\Models\ServiceCategory;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Reads and writes the services spreadsheet.
 * Layout, one sheet per category: A Category, B Service, C Price type, D Price.
 * Sheets without "Service" in B1 (instructions, pricing notes) are ignored.
 */
class ServiceSheet
{
    public const TYPES = ['fixed' => 'Fixed', 'from' => 'From', 'hourly' => 'Hourly', 'monthly' => 'Monthly', 'quote' => 'Quote'];

    /**
     * @return array{rows: array<int, array{category: string, name: string, pricing_type: string, price_cents: ?int, is_from: bool, unit: ?string, sheet: string, row: int}>, errors: array<int, array{sheet: string, row: int, message: string}>}
     */
    public static function read(string $path): array
    {
        $book = IOFactory::load($path);
        $rows = [];
        $errors = [];
        $seen = [];

        foreach ($book->getWorksheetIterator() as $sheet) {
            $title = trim($sheet->getTitle());
            if (stripos((string) $sheet->getCell('B1')->getValue(), 'service') === false) {
                continue;
            }

            $highest = $sheet->getHighestDataRow();
            for ($r = 2; $r <= $highest; $r++) {
                $category = trim((string) $sheet->getCell("A{$r}")->getFormattedValue()) ?: $title;
                $name = trim((string) $sheet->getCell("B{$r}")->getFormattedValue());
                $type = trim((string) $sheet->getCell("C{$r}")->getFormattedValue());
                $price = trim((string) $sheet->getCell("D{$r}")->getFormattedValue());

                if ($name === '' && $type === '' && $price === '') {
                    continue;
                }
                if ($name === '') {
                    $errors[] = ['sheet' => $title, 'row' => $r, 'message' => 'Service name is empty'];

                    continue;
                }
                if (mb_strlen($name) > 160 || mb_strlen($category) > 120) {
                    $errors[] = ['sheet' => $title, 'row' => $r, 'message' => "\"{$name}\" is too long"];

                    continue;
                }

                $parsed = self::parsePrice($type, $price);
                if ($parsed === null) {
                    $errors[] = ['sheet' => $title, 'row' => $r, 'message' => "Could not read the price \"{$price}\" for \"{$name}\""];

                    continue;
                }

                $key = mb_strtolower($category).'|'.mb_strtolower($name);
                if (isset($seen[$key])) {
                    $errors[] = ['sheet' => $title, 'row' => $r, 'message' => "\"{$name}\" appears twice in {$category}"];

                    continue;
                }
                $seen[$key] = true;

                $rows[] = ['category' => $category, 'name' => $name, 'sheet' => $title, 'row' => $r] + $parsed;
            }
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /** Understands "Fixed"/"From"/"Hourly"/"Monthly"/"Quote" and prices like "450", "R1 500", "From R650", "R450/hr", "R750 / month". */
    public static function parsePrice(string $type, string $price): ?array
    {
        $type = mb_strtolower(trim($type));
        $raw = mb_strtolower(trim($price));

        if ($type === 'quote' || $raw === '' || str_contains($raw, 'quote') || $raw === 'tbc') {
            if ($raw !== '' && ! str_contains($raw, 'quote') && $raw !== 'tbc' && $type !== 'quote') {
                return null;
            }

            return ['pricing_type' => 'quote', 'price_cents' => null, 'is_from' => false, 'unit' => null];
        }

        if (! preg_match('/(\d[\d\s,]*(?:\.\d{1,2})?)/', $raw, $m)) {
            return null;
        }
        $cents = (int) round(((float) preg_replace('/[\s,]/', '', $m[1])) * 100);
        if ($cents <= 0) {
            return null;
        }

        // A unit written after the price: "R450/hr", "R750 / month", "From R450 per post".
        $unit = null;
        if (preg_match('/(?:\/|\bper\b)\s*([a-z][a-z ]{0,24})\s*$/', $raw, $u)) {
            $unit = trim($u[1]);
        }
        $hourly = $type === 'hourly' || in_array($unit, ['hr', 'hrs', 'hour', 'hours'], true);
        $monthly = $type === 'monthly' || in_array($unit, ['month', 'months', 'pm', 'mo'], true);
        $from = $type === 'from' || str_starts_with($raw, 'from');

        return match (true) {
            $hourly => ['pricing_type' => 'hourly', 'price_cents' => $cents, 'is_from' => $from, 'unit' => 'hour'],
            $monthly => ['pricing_type' => 'monthly', 'price_cents' => $cents, 'is_from' => $from, 'unit' => 'month'],
            $from => ['pricing_type' => 'from', 'price_cents' => $cents, 'is_from' => true, 'unit' => $unit],
            default => ['pricing_type' => 'fixed', 'price_cents' => $cents, 'is_from' => false, 'unit' => $unit],
        };
    }

    /** Compare the sheet with what is live. Nothing is changed here. */
    public static function diff(array $rows): array
    {
        $categories = ServiceCategory::with('services')->get();
        $catByName = $categories->keyBy(fn ($c) => mb_strtolower($c->name));
        $inSheet = [];
        $result = ['new_categories' => [], 'new' => [], 'changed' => [], 'shown_again' => [], 'unchanged' => 0, 'missing' => []];

        foreach ($rows as $row) {
            $catKey = mb_strtolower($row['category']);
            $category = $catByName->get($catKey);
            if (! $category && ! in_array($row['category'], $result['new_categories'], true)) {
                $result['new_categories'][] = $row['category'];
            }

            $service = $category?->services->first(fn ($s) => mb_strtolower($s->name) === mb_strtolower($row['name']));
            if (! $service) {
                $result['new'][] = $row;

                continue;
            }
            $inSheet[$service->id] = true;

            $sameStoredPrice = $service->pricing_type === $row['pricing_type'] && $service->price_cents === $row['price_cents']
                && $service->is_from === $row['is_from'] && $service->unit === $row['unit'];
            if (! $sameStoredPrice) {
                $result['changed'][] = $row + ['service_id' => $service->id, 'old_label' => $service->priceLabel(), 'new_label' => Money::label($row['price_cents'], $row['is_from'], $row['unit'])];
            }
            if (! $service->is_active) {
                // It was hidden, and it is back in the sheet.
                $result['shown_again'][] = $row + ['service_id' => $service->id];
            }
            if ($sameStoredPrice && $service->is_active) {
                $result['unchanged']++;
            }
        }

        foreach ($categories as $category) {
            foreach ($category->services as $service) {
                if ($service->is_active && ! isset($inSheet[$service->id])) {
                    $result['missing'][] = ['service_id' => $service->id, 'category' => $category->name, 'name' => $service->name];
                }
            }
        }

        return $result;
    }

    /** The current catalogue as a spreadsheet in the import layout. */
    public static function export(): string
    {
        $book = new Spreadsheet;
        $help = $book->getActiveSheet();
        $help->setTitle('How to use');
        $lines = [
            'Services list for the client portal',
            '',
            'Each sheet is one category. Columns: Category, Service, Price type, Price.',
            'Price type is one of: Fixed, From, Hourly, Monthly, Quote.',
            'Price is the amount in rand, for example 450, R1 500, From R650 or R450 / hour. Leave it empty for Quote.',
            'To add a service, add a row. To add a category, copy a sheet and change the Category column.',
            'Services you remove from the sheet are hidden in the portal, never deleted.',
            'Upload this file in Admin > Services > Import from Excel. You will see every change before it is applied.',
        ];
        foreach ($lines as $i => $line) {
            $help->setCellValue('A'.($i + 1), $line);
        }
        $help->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $help->getColumnDimension('A')->setWidth(100);

        $used = [];
        foreach (ServiceCategory::with('services')->orderBy('sort_order')->get() as $category) {
            $title = self::sheetTitle($category->name, $used);
            $sheet = $book->createSheet()->setTitle($title);
            $sheet->fromArray(['Category', 'Service', 'Price type', 'Price'], null, 'A1');
            $sheet->getStyle('A1:D1')->getFont()->setBold(true);
            $r = 2;
            foreach ($category->services->where('is_active', true) as $service) {
                $sheet->fromArray([
                    $category->name,
                    $service->name,
                    self::TYPES[$service->pricing_type] ?? 'Quote',
                    // Written the way people read it ("From R499 / month"), and read back the same way.
                    $service->price_cents === null ? '' : $service->priceLabel(),
                ], null, "A{$r}");
                $r++;
            }
            foreach (['A' => 32, 'B' => 48, 'C' => 14, 'D' => 12] as $col => $width) {
                $sheet->getColumnDimension($col)->setWidth($width);
            }
            $sheet->freezePane('A2');
        }

        $path = tempnam(sys_get_temp_dir(), 'services').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }

    /** Excel sheet titles: at most 31 characters, no : \ / ? * [ ], and unique. */
    private static function sheetTitle(string $name, array &$used): string
    {
        $base = mb_substr(trim(preg_replace('/[:\\\\\/?*\[\]]/', ' ', $name)), 0, 31) ?: 'Category';
        $title = $base;
        $n = 2;
        while (in_array(mb_strtolower($title), $used, true)) {
            $title = mb_substr($base, 0, 27).' '.$n++;
        }
        $used[] = mb_strtolower($title);

        return $title;
    }
}
