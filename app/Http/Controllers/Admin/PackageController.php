<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Package;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        ]);

        $cents = Money::toCents($data['price'] ?? null);

        return [
            'name' => $data['name'],
            'price_cents' => $cents,
            'is_from' => $cents !== null && $request->boolean('is_from'),
            'is_featured' => $request->boolean('is_featured'),
            'sort_order' => (int) ($data['sort_order'] ?? Package::max('sort_order') + 1),
            'unit' => $cents === null ? null : ($data['unit'] ?? null),
            'includes' => collect(preg_split('/\r\n|\r|\n/', (string) ($data['includes'] ?? '')))->map(fn ($l) => trim($l))->filter()->values()->all(),
        ];
    }
}
