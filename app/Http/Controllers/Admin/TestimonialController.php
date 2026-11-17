<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    public function index()
    {
        $received = Testimonial::with('client', 'project')->whereIn('status', ['received', 'featured'])->latest('received_at')->get();

        return view('admin.testimonials.index', [
            'received' => $received,
            'waiting' => Testimonial::with('client', 'project')->where('status', 'requested')->latest('requested_at')->get(),
            'average' => $received->avg('rating'),
        ]);
    }

    /** Star it for the website. Only testimonials the client allowed to be published. */
    public function feature(Request $request, Testimonial $testimonial)
    {
        abort_unless($testimonial->can_publish && $testimonial->status !== 'requested', 409);
        $testimonial->status = $testimonial->status === 'featured' ? 'received' : 'featured';
        $testimonial->save();

        return back()->with('status', $testimonial->status === 'featured' ? 'Marked as a favourite for the website.' : 'Removed from favourites.');
    }
}
