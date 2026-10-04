@props(['order'])
{{-- Anneau : part des étapes réellement faites sur le total. Aucune valeur simulée. --}}
@php
    $total = $order->steps->count();
    $done = $order->steps->where('status', 'done')->count();
    $pct = $total ? (int) round($done / $total * 100) : 0;
    $color = match ($order->status) { 'completed' => '#16a34a', 'active' => '#0B5470', default => '#DC2626' };
@endphp
<div class="ring" style="--p:{{ $pct }};--c:{{ $color }}" title="{{ $done }} étape(s) sur {{ $total }}"><span>{{ $pct }}%</span></div>
