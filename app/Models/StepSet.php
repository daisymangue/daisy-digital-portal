<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A ready-made list of project steps, such as "Website" or "Quick job". */
class StepSet extends Model
{
    protected $fillable = ['name', 'steps', 'sort_order'];

    protected function casts(): array
    {
        return ['steps' => 'array', 'sort_order' => 'integer'];
    }

    /** "First design ✓" style summary for lists. */
    public function summary(): string
    {
        return collect($this->steps)->map(fn ($s) => $s['title'].(! empty($s['approval']) ? ' ✓' : ''))->implode(' → ');
    }

    /** Text for the edit box: one step per line, a trailing "*" means the client approves it. */
    public function asText(): string
    {
        return collect($this->steps)->map(fn ($s) => $s['title'].(! empty($s['approval']) ? ' *' : ''))->implode("\n");
    }

    public static function parse(string $text): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($l) => trim($l, " \t-•"))->filter()
            ->map(fn ($l) => ['title' => mb_substr(rtrim(rtrim($l, '*')), 0, 200), 'approval' => str_ends_with($l, '*')])
            ->filter(fn ($s) => $s['title'] !== '')->take(20)->values()->all();
    }

    /**
     * The set that fits a quote: the package's set first, otherwise the biggest set among the
     * chosen services' categories. Null when nothing is linked.
     */
    public static function forQuote(Quote $quote): ?self
    {
        $request = $quote->serviceRequest;
        if (! $request) {
            return null;
        }

        if ($request->package_id && ($set = Package::find($request->package_id)?->stepSet)) {
            return $set;
        }

        $categoryIds = Service::whereIn('id', $request->services()->whereNotNull('service_id')->pluck('service_id'))->pluck('service_category_id');

        return self::whereIn('id', ServiceCategory::whereIn('id', $categoryIds)->whereNotNull('step_set_id')->pluck('step_set_id'))
            ->get()->sortByDesc(fn ($s) => count($s->steps))->first();
    }

    /** Starting links for a fresh install, matched on names. The admin can change them. */
    public static function linkDefaults(): void
    {
        $by = self::pluck('id', 'name');
        $pick = fn (string $name, array $rules) => $by[collect($rules)->first(fn ($set, $pattern) => preg_match($pattern, $name)) ?? 'Quick job'] ?? null;

        foreach (ServiceCategory::whereNull('step_set_id')->get() as $c) {
            $c->step_set_id = $pick($c->name, ['/Website Design/' => 'Website', '/Graphic Design/' => 'Logo and design', '/Marketing/' => 'Social media and marketing']);
            $c->save();
        }
        foreach (Package::whereNull('step_set_id')->get() as $p) {
            $p->step_set_id = $pick($p->name, ['/Marketing/' => 'Social media and marketing', '/Graphic Design/' => 'Logo and design', '/Starter|Essentials|Growth|Launch/' => 'Website']);
            $p->save();
        }
    }
}
