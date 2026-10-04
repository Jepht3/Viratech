@extends('layouts.app')
@section('title', 'File de validation')
@section('heading')<div class="mut sm">Console opérateur</div><h1>File de validation</h1>@endsection

@section('content')
<div class="grid g4">
    <div class="tile green"><span class="ic">$</span><div><div class="t">Volume du jour</div><div class="v num">{{ number_format($volumeToday, 0, ',', ' ') }} $</div></div><div class="f"><span>Commandes créées aujourd'hui</span></div></div>
    <div class="tile cyan"><span class="ic">%</span><div><div class="t">Frais encaissés aujourd'hui</div><div class="v num">{{ number_format($feesToday, 2, ',', ' ') }} $</div></div><div class="f"><span>Commandes terminées</span></div></div>
    <div class="tile amber"><span class="ic">⏱</span><div><div class="t">Délai moyen de traitement</div><div class="v num">{{ $avgMinutes !== null ? $avgMinutes.' min' : '—' }}</div></div><div class="f"><span>Sur les dernières commandes</span></div></div>
    <div class="tile navy"><span class="ic">☰</span><div><div class="t">Commandes en cours</div><div class="v num">{{ $active->count() }}</div></div><div class="f"><span>À traiter</span><a href="/admin/commandes?statut=active">→</a></div></div>
</div>

<div class="grid g7" style="margin-top:18px">
    <div class="card">
        <div class="row sp" style="margin-bottom:14px"><b style="font-size:16px">À traiter</b><a href="/admin/commandes" class="view">Voir tout</a></div>
        @forelse($active as $o)
            @php
                $s = $o->currentStep();
                $late = $s?->started_at && $s->started_at->diffInMinutes() > $o->corridor->eta_max_minutes;
            @endphp
            <a class="obs" href="/admin/commandes/{{ $o->reference }}">
                <x-ring :order="$o" />
                <div style="flex:1;min-width:0">
                    <b class="sm">{{ $o->reference }} · {{ $o->corridor->label }}</b>
                    <div class="xs mut">{{ $o->user->name }} · N{{ $o->user->kyc_level }} · {{ number_format($o->amount, 2, ',', ' ') }} $</div>
                    <div class="xs" style="color:{{ $s?->status === 'blocked' ? 'var(--badfg)' : 'var(--waitfg)' }}">{{ $s?->pending_label }}</div>
                </div>
                <div class="right"><span class="pill {{ $late ? 'bad' : 'wait' }} xs">{{ $s?->started_at?->diffForHumans(null, true) ?? '—' }}{{ $late ? ' ⚠' : '' }}</span></div>
            </a>
        @empty
            <div class="empty">Aucune commande à traiter. 🎉</div>
        @endforelse
    </div>
    <div class="card">
        <b style="font-size:16px">Volume par échange (ce mois)</b>
        @php $max = max($byCorridor->max('amount') ?: 1, 1); @endphp
        <div class="bars" style="margin-top:8px">
            @forelse($byCorridor as $i => $c)
                <div><div class="row sp sm"><span>{{ $c['label'] }}</span><b class="num">{{ number_format($c['amount'], 0, ',', ' ') }} $</b></div><div class="bar"><i class="{{ $i % 2 ? 'n' : '' }}" style="width:{{ $c['amount'] / $max * 100 }}%"></i></div></div>
            @empty
                <div class="empty">Pas encore de volume ce mois-ci.</div>
            @endforelse
        </div>
    </div>
</div>

<div class="grid g7" style="margin-top:18px">
    <div class="card"><b style="font-size:16px">Volume par mois</b><div style="margin-top:10px"><x-linechart :series="$series" :labels="$labels" /></div></div>
    <div class="card">
        <b style="font-size:16px">Mouvements par canal</b>
        <div class="xs mut">D'après les opérations enregistrées : à comparer aux soldes réels de vos comptes.</div>
        <div class="list sm" style="margin-top:6px">
            @forelse($channels as $ch)
                <div><x-chan :kind="$ch['kind']" /><div style="flex:1"><b>{{ $ch['label'] }}</b></div><b class="num" style="color:{{ $ch['amount'] >= 0 ? 'var(--okfg)' : 'var(--badfg)' }}">{{ $ch['amount'] >= 0 ? '+' : '' }}{{ number_format($ch['amount'], 2, ',', ' ') }} $</b></div>
            @empty<div class="empty">Aucun mouvement.</div>@endforelse
            <div><div class="dot gen">Σ</div><div style="flex:1"><b>Dû aux clients</b><div class="xs mut">Fonds reçus, pas encore versés</div></div><b class="num">{{ number_format($owed, 2, ',', ' ') }} $</b></div>
        </div>
    </div>
</div>
@endsection
