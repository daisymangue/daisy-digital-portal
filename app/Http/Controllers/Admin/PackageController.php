<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Package;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PackageController extends Controller
{
    public function index()
    {
        return view('admin.packages.index', ['packages' => Package::orderBy('sort_order')->get()]);
    }

    public function store(Request $request)
    {
        $package = Package::create($this->data($request));
        AuditLog::record('catalogue.package_created', $package, ['name' => $package->name]);

        return back()->with('status', 'Package added.');
    }

    public function update(Request $request, Package $package)
    {
        $package->update($this->data($request) + ['is_active' => $request->boolean('is_active')]);
        AuditLog::record('catalogue.package_updated', $package, ['name' => $package->name]);

        return back()->with('status', "Saved: {$package->name}");
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'price' => ['nullable', 'string', 'max:20'],
            'unit' => ['nullable', Rule::in(['month'])],
            'includes' => ['nullable', 'string', 'max:3000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'sale_price' => ['nullable', 'string', 'max:20'],
            'sale_ends_on' => ['nullable', 'date'],
        ]);

        $cents = Money::toCents($data['price'] ?? null);
        $sale = Money::toCents($data['sale_price'] ?? null);
        if ($sale !== null && ($cents === null || $sale >= $cents)) {
            throw ValidationException::withMessages(['sale_price' => 'The sale price must be lower than the normal price. Leave it empty for no sale.']);
        }

        return [
            'name' => $data['name'],
            'price_cents' => $cents,
            'sale_price_cents' => $sale,
            'sale_ends_on' => $sale !== null ? ($data['sale_ends_on'] ?? null) : null,
            'is_from' => $cents !== null && $request->boolean('is_from'),
            'is_featured' => $request->boolean('is_featured'),
            'sort_order' => (int) ($data['sort_order'] ?? Package::max('sort_order') + 1),
            'unit' => $cents === null ? null : ($data['unit'] ?? null),
            'includes' => collect(preg_split('/\r\n|\r|\n/', (string) ($data['includes'] ?? '')))->map(fn ($l) => trim($l))->filter()->values()->all(),
        ];
    }
}
