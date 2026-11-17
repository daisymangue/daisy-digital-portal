<?php

namespace App\Support;

use App\Models\CategoryQuestion;
use App\Models\Client;
use App\Models\Counter;
use App\Models\Package;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Creates service requests, whether a client submits them or the admin captures one for a client. */
class RequestBuilder
{
    public const FILE_TYPES = 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip';

    /** Validation rules shared by the client form and the admin form. */
    public static function rules(): array
    {
        return [
            'package_id' => ['nullable', 'integer'],
            'services' => ['nullable', 'array', 'max:60'],
            'services.*' => ['integer'],
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'special_details' => ['nullable', 'string', 'max:5000'],
            'budget_range' => ['nullable', Rule::in(ServiceRequest::BUDGETS)],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
            'files' => ['nullable', 'array', 'max:8'],
            'files.*' => ['file', 'max:10240', 'mimes:'.self::FILE_TYPES],
        ];
    }

    public static function messages(): array
    {
        return [
            'files.*.max' => 'Each file can be up to 10 MB.',
            'files.max' => 'You can add up to 8 files in total.',
            'files.*.mimes' => 'That file type is not supported. Please upload images, PDFs, Office documents or a zip.',
            'needed_by.after_or_equal' => 'The "needed by" date cannot be in the past.',
        ];
    }

    /** What the request form needs: active categories with services and questions, and packages. */
    public static function formData(): array
    {
        return [
            'categories' => ServiceCategory::where('is_active', true)
                ->with([
                    'services' => fn ($q) => $q->where('is_active', true),
                    'questions' => fn ($q) => $q->where('is_active', true),
                ])
                ->orderBy('sort_order')->get(),
            'packages' => Package::where('is_active', true)->orderByDesc('is_featured')->orderBy('sort_order')->get(),
            'popular' => Service::where('is_active', true)->where('is_popular', true)
                ->whereHas('category', fn ($q) => $q->where('is_active', true))->orderBy('name')->get(),
        ];
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  bool  $enforceRequired  Clients must answer required questions. The admin may not have every answer yet.
     */
    public static function create(Client $client, User $by, array $data, array $files, bool $enforceRequired, string $source, string $status): ServiceRequest
    {
        // Only active catalogue items count, whatever the browser sent.
        $services = Service::with('category')->where('is_active', true)
            ->whereIn('id', $data['services'] ?? [])
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->get();
        $package = empty($data['package_id']) ? null : Package::where('is_active', true)->find($data['package_id']);
        $description = trim($data['description'] ?? '');
        $special = trim($data['special_details'] ?? '');

        if ($special !== '' && mb_strlen($special) < 10) {
            throw ValidationException::withMessages(['special_details' => 'Please describe the special request in a sentence or two.']);
        }
        if ($services->isEmpty() && ! $package && $special === '' && mb_strlen($description) < 10) {
            throw ValidationException::withMessages([
                'services' => 'Please choose a package or a service, or describe what you need as a special request.',
            ]);
        }

        // Questions belong to the categories of the chosen services.
        $questions = CategoryQuestion::with('category')->where('is_active', true)
            ->whereIn('service_category_id', $services->pluck('service_category_id')->unique())
            ->orderBy('sort_order')->get();

        $answers = [];
        foreach ($questions as $q) {
            $value = trim((string) ($data['answers'][$q->id] ?? ''));
            if ($value === '' && $q->is_required && $enforceRequired) {
                throw ValidationException::withMessages(["answers.{$q->id}" => "Please answer: {$q->label}"]);
            }
            if ($q->type === 'select' && $value !== '' && ! in_array($value, $q->options ?? [], true)) {
                $value = '';
            }
            if ($q->type === 'yesno' && ! in_array($value, ['Yes', 'No'], true)) {
                $value = '';
            }
            if ($value !== '') {
                $answers[] = ['category_question_id' => $q->id, 'category_name' => $q->category->name, 'question' => $q->label, 'answer' => $value];
            }
        }

        return DB::transaction(function () use ($client, $by, $services, $package, $description, $special, $data, $answers, $files, $source, $status) {
            $sr = new ServiceRequest(['description' => $description ?: null, 'package_id' => $package?->id, 'package_name' => $package?->name]);
            $sr->special_details = $special ?: null;
            // Budget and deadline only mean something alongside a special request.
            $sr->budget_range = $special !== '' ? ($data['budget_range'] ?? null) : null;
            $sr->needed_by = $special !== '' ? ($data['needed_by'] ?? null) : null;
            $sr->client_id = $client->id;
            $sr->user_id = $by->id;
            $sr->source = $source;
            $sr->status = $status;
            $sr->submitted_at = now();
            $sr->number = 'REQ-'.str_pad((string) Counter::next('service_request'), 4, '0', STR_PAD_LEFT);
            $sr->save();

            // A snapshot of each service, so later price edits never change an old request.
            $sr->services()->createMany($services->map(fn (Service $s) => [
                'service_id' => $s->id,
                'category_name' => $s->category->name,
                'name' => $s->name,
                'pricing_type' => $s->pricing_type,
                'price_cents' => $s->price_cents,
                'is_from' => $s->is_from,
                'unit' => $s->unit,
            ])->all());

            $sr->answers()->createMany($answers);

            foreach ($files as $upload) {
                $sr->files()->create([
                    'client_id' => $client->id,
                    'uploaded_by' => $by->id,
                    'original_name' => mb_substr($upload->getClientOriginalName(), 0, 200),
                    'path' => $upload->store("clients/{$client->id}/requests/{$sr->id}", 'local'),
                    'mime' => $upload->getMimeType(),
                    'size' => $upload->getSize(),
                ]);
            }

            return $sr;
        });
    }
}
