@extends('layouts.app')
@section('title', 'Accueil')
@section('heading')
    <div class="mut sm">{{ now()->translatedFormat('l j F') }}</div>
    <h1>Bonjour {{ explode(' ', $user->name)[0] }} 👋</h1>
@endsection
@section('actions')<a class="btn amber" href="/echange/nouveau">⇄ Nouvel échange</a>@endsection

@section('content')
@php $limit = $user->monthlyLimit(); @endphp
<div class="grid g4">
    <div class="tile teal"><span class="ic">$</span><div><div class="t">À recevoir (en cours)</div><div class="v num">{{ number_format($toReceive, 2, ',', ' ') }} $</div></div><div class="f"><span>{{ $activeCount }} commande(s) en cours</span><a href="/commandes">→</a></div></div>
    <div class="tile amber"><span class="ic">✓</span><div><div class="t">Reçu ce mois</div><div class="v num">{{ number_format($receivedMonth, 2, ',', ' ') }} $</div></div><div class="f"><span>Commandes terminées</span><a href="/commandes">→</a></div></div>
    <div class="tile teal"><span class="ic">◎</span><div><div class="t">Plafond mensuel</div><div class="v num">{{ $limit ? number_format($usedMonth, 0, ',', ' ').' / '.number_format($limit, 0, ',', ' ').' $' : 'Sur mesure' }}</div></div><div class="f"><span>Niveau de vérification {{ $user->kyc_level }}</span></div></div>
    <div class="tile navy"><span class="ic">★</span><div><div class="t">Moyens de réception</div><div class="v num">{{ $methods->count() }}</div></div><div class="f"><span>Enregistrés</span><a href="/moyens-de-reception">→</a></div></div>
</div>

<div class="grid g7" style="margin-top:18px">
    <div class="card">
        <div class="row sp" style="margin-bottom:14px"><b style="font-size:16px">Mes commandes</b><a href="/commandes" class="view">Tout voir</a></div>
        @forelse($recent as $o)
            <a class="obs" href="/commandes/{{ $o->reference }}">
                <x-ring :order="$o" />
                <div style="flex:1;min-width:0"><b class="sm">{{ $o->corridor->label }}</b><div class="xs mut">{{ $o->reference }} · {{ $o->created_at->format('d/m/Y H:i') }}</div>
                    @if($o->isActive())<div class="xs" style="color:var(--waitfg)">{{ $o->currentStep()?->pending_label }}</div>@endif</div>
                <div class="right"><b class="num">{{ number_format($o->net_amount, 2, ',', ' ') }} $</b><div><span class="pill {{ ['completed' => 'ok', 'active' => 'wait'][$o->status] ?? 'bad' }} xs">{{ $o->statusLabel() }}</span></div></div>
            </a>
        @empty
            <div class="empty">Pas encore de commande.<br><a href="/echange/nouveau" class="view">Faire un premier échange</a></div>
        @endforelse
    </div>
    <div class="card">
        <b style="font-size:16px">Frais et minimums</b>
        <div class="list sm" style="margin-top:6px">
            @foreach($corridors as $c)
                <div>
                    <x-chan :kind="$c->source_kind" /><span class="mut">→</span><x-chan :kind="$c->target_kind" />
                    <div style="flex:1"><b>{{ $c->label }}</b>
                        @if($c->coming_soon)<div class="xs mut">Bientôt disponible</div>
                        @else<div class="xs mut">Min {{ number_format($c->min_amount, 0) }} $ · {{ $c->etaLabel() }}</div>@endif</div>
                    @if($c->coming_soon)<span class="pill wait xs">Bientôt</span>
                    @else<b class="num">{{ $c->tiers->count() > 1 ? ($c->tiers->first()->percent + 0).' à '.($c->tiers->last()->percent + 0) : ($c->tiers->first()->percent + 0) }} %@if($c->fixed_fee > 0) + {{ $c->fixed_fee + 0 }} $@endif</b>@endif
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="grid g7" style="margin-top:18px">
    <div class="card"><b style="font-size:16px">Montants reçus par mois</b><div style="margin-top:10px"><x-linechart :series="$series" :labels="$labels" /></div></div>
    <div class="card">
        <div class="row sp"><b style="font-size:16px">Mes moyens de réception</b><a href="/moyens-de-reception" class="view">Gérer</a></div>
        <div class="list sm" style="margin-top:6px">
            @forelse($methods as $m)
                <div><x-chan :kind="$m->kind" /><div style="flex:1"><b>{{ $m->kindLabel() }}</b><div class="xs mut">{{ $m->masked() }}</div></div><span class="pill {{ $m->is_verified ? 'ok' : 'wait' }} xs">{{ $m->is_verified ? 'Vérifié' : 'À vérifier' }}</span></div>
            @empty<div class="empty">Aucun moyen enregistré.</div>@endforelse
        </div>
    </div>
</div>
@endsection
