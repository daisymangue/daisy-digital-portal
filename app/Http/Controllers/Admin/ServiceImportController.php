<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\ServiceSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Update the service list from an Excel file: upload, preview every change, then apply. */
class ServiceImportController extends Controller
{
    public function export()
    {
        AuditLog::record('catalogue.exported');

        return response()->download(ServiceSheet::export(), 'Daisy-services-'.now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend();
    }

    public function create()
    {
        return view('admin.services.import');
    }

    /** Read the file and show what would change. Nothing is saved yet. */
    public function preview(Request $request)
    {
        $request->validate(
            ['sheet' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv']],
            ['sheet.mimes' => 'Please upload an Excel file (.xlsx).', 'sheet.max' => 'The file can be up to 5 MB.'],
        );

        $token = (string) Str::uuid();
        $path = $request->file('sheet')->storeAs('imports', $token.'.'.$request->file('sheet')->getClientOriginalExtension(), 'local');

        try {
            $read = ServiceSheet::read(Storage::disk('local')->path($path));
        } catch (\Throwable $e) {
            report($e);
            Storage::disk('local')->delete($path);

            return back()->withErrors(['sheet' => 'That file could not be read. Make sure it is an Excel file in the same layout as "Download current list".']);
        }

        if ($read['rows'] === []) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['sheet' => 'No services were found. Each sheet needs the headings Category, Service, Price type and Price in the first row.']);
        }

        session(['service_import' => ['token' => $token, 'path' => $path, 'name' => $request->file('sheet')->getClientOriginalName()]]);

        return view('admin.services.import-preview', [
            'diff' => ServiceSheet::diff($read['rows']),
            'errors_in_sheet' => $read['errors'],
            'token' => $token,
            'fileName' => $request->file('sheet')->getClientOriginalName(),
        ]);
    }

    public function apply(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'uuid']]);
        $import = session('service_import');

        if (! $import || $import['token'] !== $data['token'] || ! Storage::disk('local')->exists($import['path'])) {
            return redirect()->route('admin.services.import')->withErrors(['sheet' => 'That upload has expired. Please upload the file again.']);
        }

        // Read again, so what is applied is exactly the file that was previewed.
        $read = ServiceSheet::read(Storage::disk('local')->path($import['path']));
        $diff = ServiceSheet::diff($read['rows']);
        $hideMissing = $request->boolean('hide_missing');

        DB::transaction(function () use ($diff, $hideMissing) {
            $categories = ServiceCategory::all()->keyBy(fn ($c) => mb_strtolower($c->name));

            foreach ($diff['new'] as $row) {
                $key = mb_strtolower($row['category']);
                if (! $categories->has($key)) {
                    $slug = Str::slug($row['category']) ?: 'category';
                    if (ServiceCategory::where('slug', $slug)->exists()) {
                        $slug .= '-'.Str::lower(Str::random(4));
                    }
                    $categories->put($key, ServiceCategory::create([
                        'name' => $row['category'], 'slug' => $slug, 'sort_order' => (ServiceCategory::max('sort_order') ?? 0) + 1,
                    ]));
                }
                $category = $categories->get($key);
                Service::create([
                    'service_category_id' => $category->id,
                    'name' => $row['name'],
                    'pricing_type' => $row['pricing_type'],
                    'price_cents' => $row['price_cents'],
                    'is_from' => $row['is_from'],
                    'unit' => $row['unit'],
                    'sort_order' => (Service::where('service_category_id', $category->id)->max('sort_order') ?? 0) + 1,
                ]);
            }

            foreach ($diff['changed'] as $row) {
                Service::whereKey($row['service_id'])->update([
                    'pricing_type' => $row['pricing_type'], 'price_cents' => $row['price_cents'], 'is_from' => $row['is_from'], 'unit' => $row['unit'],
                ]);
            }

            Service::whereIn('id', array_column($diff['shown_again'], 'service_id'))->update(['is_active' => true]);

            if ($hideMissing) {
                // Hidden, never deleted: old requests and quotes keep their own copy anyway.
                Service::whereIn('id', array_column($diff['missing'], 'service_id'))->update(['is_active' => false]);
            }
        });

        $summary = [
            'file' => $import['name'],
            'added' => count($diff['new']),
            'price_changes' => count($diff['changed']),
            'shown_again' => count($diff['shown_again']),
            'hidden' => $hideMissing ? count($diff['missing']) : 0,
            'new_categories' => count($diff['new_categories']),
        ];
        AuditLog::record('catalogue.imported', null, $summary);

        Storage::disk('local')->delete($import['path']);
        session()->forget('service_import');

        return redirect()->route('admin.services.index')->with('status', sprintf(
            'Import done: %d added, %d price changes, %d shown again, %d hidden.',
            $summary['added'], $summary['price_changes'], $summary['shown_again'], $summary['hidden'],
        ));
    }
}
