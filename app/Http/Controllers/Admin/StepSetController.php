<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Package;
use App\Models\ServiceCategory;
use App\Models\StepSet;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Ready-made step lists. A new project from a quote picks up the set its package or services point at. */
class StepSetController extends Controller
{
    public function index()
    {
        return view('admin.step-sets.index', [
            'sets' => StepSet::orderBy('sort_order')->orderBy('name')->get(),
            'categories' => ServiceCategory::orderBy('sort_order')->get(['id', 'name', 'step_set_id']),
            'packages' => Package::orderBy('sort_order')->get(['id', 'name', 'step_set_id']),
        ]);
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'steps' => ['required', 'string', 'max:4000'],
        ], ['steps.required' => 'Add at least one step, one per line.']);

        $steps = StepSet::parse($data['steps']);
        if (! $steps) {
            throw ValidationException::withMessages(['steps' => 'Add at least one step, one per line.']);
        }

        return ['name' => $data['name'], 'steps' => $steps];
    }

    public function store(Request $request)
    {
        $set = StepSet::create($this->data($request) + ['sort_order' => (int) StepSet::max('sort_order') + 1]);
        AuditLog::record('step_set.created', $set, ['name' => $set->name]);

        return back()->with('status', 'Step set added.');
    }

    public function update(Request $request, StepSet $stepSet)
    {
        $stepSet->update($this->data($request));

        return back()->with('status', 'Step set saved. Projects that already started keep their own steps.');
    }

    public function destroy(StepSet $stepSet)
    {
        $stepSet->delete();

        return back()->with('status', 'Step set removed. Projects keep their steps.');
    }

    /** Which set each category and package uses, saved in one go. */
    public function links(Request $request)
    {
        $valid = StepSet::pluck('id')->all();
        foreach ((array) $request->input('categories', []) as $id => $setId) {
            ServiceCategory::whereKey((int) $id)->update(['step_set_id' => in_array((int) $setId, $valid, true) ? (int) $setId : null]);
        }
        foreach ((array) $request->input('packages', []) as $id => $setId) {
            Package::whereKey((int) $id)->update(['step_set_id' => in_array((int) $setId, $valid, true) ? (int) $setId : null]);
        }

        return back()->with('status', 'Saved. New projects will start with these steps.');
    }
}
