@props(['status', 'label'])
@php
    // Pairs differ in lightness as well as hue, so they stay distinguishable for colour-blind users.
    $styles = [
        'new' => 'bg-pink-100 text-pink-900',
        'reviewing' => 'bg-blue-100 text-blue-900',
        'awaiting_client' => 'bg-amber-100 text-amber-900',
        'quoted' => 'bg-violet-100 text-violet-900',
        'approved' => 'bg-green-100 text-green-900',
        'converted' => 'bg-navy text-white',
        'declined' => 'bg-sand-dark text-muted',
        'draft' => 'bg-sand-dark text-ink',
        'sent' => 'bg-blue-100 text-blue-900',
        'changes_requested' => 'bg-amber-100 text-amber-900',
        'accepted' => 'bg-green-100 text-green-900',
        'expired' => 'bg-sand-dark text-muted',
        'superseded' => 'bg-sand-dark text-muted',
        'issued' => 'bg-blue-100 text-blue-900',
        'partially_paid' => 'bg-amber-100 text-amber-900',
        'paid' => 'bg-green-100 text-green-900',
        'overdue' => 'bg-red-100 text-red-900',
        'cancelled' => 'bg-sand-dark text-muted',
        'pending' => 'bg-amber-100 text-amber-900',
        'confirmed' => 'bg-green-100 text-green-900',
        'rejected' => 'bg-red-100 text-red-900',
    ];
@endphp
<span class="pill {{ $styles[$status] ?? 'bg-sand-dark text-ink' }}">{{ $label }}</span>
