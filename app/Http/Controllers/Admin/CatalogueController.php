<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CategoryQuestion;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CatalogueController extends Controller
{
    public function index()
    {
        return view('admin.services.index', [
            'categories' => ServiceCategory::withCount(['services', 'questions'])->orderBy('sort_order')->get(),
        ]);
    }

    public function show(ServiceCategory $category)
    {
        return view('admin.services.show', ['category' => $category->load('services', 'questions')]);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'description' => ['nullable', 'string', 'max:200']]);

        $slug = Str::slug($data['name']);
        if ($slug === '' || ServiceCategory::where('slug', $slug)->exists()) {
            $slug = trim($slug.'-'.Str::lower(Str::random(5)), '-');
        }

        $category = ServiceCategory::create($data + ['slug' => $slug, 'sort_order' => (ServiceCategory::max('sort_order') ?? 0) + 1]);
        AuditLog::record('catalogue.category_created', $category, ['name' => $category->name]);

        return redirect()->route('admin.categories.show', $category)->with('status', 'Category added. Now add its services and questions.');
    }

    public function updateCategory(Request $request, ServiceCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:200'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $category->update($data + ['is_active' => $request->boolean('is_active')]);
        AuditLog::record('catalogue.category_updated', $category, ['name' => $category->name]);

        return back()->with('status', 'Category saved.');
    }

    public function storeService(Request $request, ServiceCategory $category)
    {
        $service = $category->services()->create($this->serviceData($request) + ['sort_order' => ($category->services()->max('sort_order') ?? 0) + 1]);
        AuditLog::record('catalogue.service_created', $service, ['name' => $service->name]);

        return back()->with('status', 'Service added.');
    }

    public function updateService(Request $request, Service $service)
    {
        $service->update($this->serviceData($request) + ['is_active' => $request->boolean('is_active')]);
        AuditLog::record('catalogue.service_updated', $service, ['name' => $service->name]);

        return back()->with('status', "Saved: {$service->name}");
    }

    public function storeQuestion(Request $request, ServiceCategory $category)
    {
        $question = $category->questions()->create($this->questionData($request) + ['sort_order' => ($category->questions()->max('sort_order') ?? 0) + 1]);
        AuditLog::record('catalogue.question_created', $question);

        return back()->with('status', 'Question added.');
    }

    public function updateQuestion(Request $request, CategoryQuestion $question)
    {
        $question->update($this->questionData($request) + ['is_active' => $request->boolean('is_active')]);
        AuditLog::record('catalogue.question_updated', $question);

        return back()->with('status', 'Question saved.');
    }

    public function destroyQuestion(CategoryQuestion $question)
    {
        // Past answers keep their own copy of the question text, so removing it is safe.
        AuditLog::record('catalogue.question_deleted', $question, ['label' => $question->label]);
        $question->delete();

        return back()->with('status', 'Question removed.');
    }

    private function serviceData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'pricing_type' => ['required', Rule::in(array_keys(Service::PRICING_TYPES))],
            'price' => ['nullable', 'string', 'max:20'],
            'unit' => ['nullable', 'string', 'max:30'],
        ]);

        $cents = $data['pricing_type'] === 'quote' ? null : Money::toCents($data['price'] ?? null);
        $unit = match ($data['pricing_type']) {
            'hourly' => 'hour',
            'monthly' => 'month',
            'quote' => null,
            default => $data['unit'] ?? null,
        };

        return [
            'name' => $data['name'],
            'pricing_type' => $cents === null ? 'quote' : $data['pricing_type'],
            'price_cents' => $cents,
            // "From" is its own pricing type. Hourly and monthly prices can also be "from" (e.g. From R499 / month).
            'is_from' => $cents !== null && ($data['pricing_type'] === 'from'
                || (in_array($data['pricing_type'], ['hourly', 'monthly'], true) && $request->boolean('is_from'))),
            'unit' => $cents === null ? null : $unit,
        ];
    }

    private function questionData(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(CategoryQuestion::TYPES))],
            'options' => ['nullable', 'string', 'max:1000'],
        ]);

        $options = collect(preg_split('/[\r\n,]+/', (string) ($data['options'] ?? '')))
            ->map(fn ($o) => trim($o))->filter()->unique()->values()->all();

        if ($data['type'] === 'select' && count($options) < 2) {
            abort(back()->withErrors(['options' => 'A "choose one" question needs at least two options.'])->withInput());
        }

        return [
            'label' => $data['label'],
            'type' => $data['type'],
            'options' => $data['type'] === 'select' ? $options : null,
            'is_required' => $request->boolean('is_required'),
        ];
    }
}
