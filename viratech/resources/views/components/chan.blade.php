@props(['kind', 'small' => false])
@php
    $map = ['paypal' => 'pp', 'equity' => 'eq', 'mpesa' => 'mp', 'airtel' => 'ar', 'orange' => 'og', 'afrimoney' => 'af', 'flexpay' => 'fx'];
@endphp
@if($kind === 'mobile_money')
    <span class="row" style="gap:4px"><span class="dot mp {{ $small ? 'sm' : '' }}"></span><span class="dot ar {{ $small ? 'sm' : '' }}"></span></span>
@elseif(isset($map[$kind]))
    <span class="dot {{ $map[$kind] }} {{ $small ? 'sm' : '' }}" title="{{ \App\Models\CompanyAccount::KINDS[$kind] ?? ucfirst($kind) }}"></span>
@else
    <span class="dot gen {{ $small ? 'sm' : '' }}">{{ $kind === 'crypto' ? '₮' : strtoupper(substr($kind, 0, 2)) }}</span>
@endif
