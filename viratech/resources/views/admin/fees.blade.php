@extends('layouts.app')
@section('title', 'Frais et minimums')
@section('heading')<h1>Frais et minimums</h1><div class="mut sm">Modifiables à tout moment. Le palier retenu est celui du montant total (pas par tranche). Les changements s'appliquent aux nouvelles commandes.</div>@endsection

@section('content')
<div class="grid g2">
@foreach($corridors as $c)
    <form method="post" action="/admin/frais/{{ $c->id }}" class="card">@csrf
        <div class="row sp"><div class="row"><x-chan :kind="$c->source_kind" /><span class="mut">→</span><x-chan :kind="$c->target_kind" /><b>{{ $c->label }}</b></div>
            @if($c->coming_soon)<span class="pill wait">Bientôt</span>@else<label class="row" style="margin:0;font-weight:500"><input type="checkbox" name="is_active" value="1" style="width:auto" @checked($c->is_active)> Actif</label>@endif</div>
        <div class="grid g2" style="gap:12px">
            <div><label>Montant minimum ($)</label><input type="number" step="0.01" name="min_amount" value="{{ $c->min_amount + 0 }}" required></div>
            <div><label>Frais fixes ($)</label><input type="number" step="0.01" name="fixed_fee" value="{{ $c->fixed_fee + 0 }}" required></div>
            <div><label>Délai min (minutes)</label><input type="number" name="eta_min_minutes" value="{{ $c->eta_min_minutes }}" required></div>
            <div><label>Délai max (minutes)</label><input type="number" name="eta_max_minutes" value="{{ $c->eta_max_minutes }}" required></div>
            @if($c->isWithdrawal() && ! $c->coming_soon)<div style="grid-column:1/-1"><label>Délai de sécurité standard (jours) · avant tout versement</label><input type="number" step="0.5" name="hold_days" value="{{ $c->hold_minutes / 1440 }}"><div class="xs mut">Protège des litiges et rétrofacturations PayPal. Client nouveau ou non vérifié : le double. Client vérifié avec 10 échanges réussis : la moitié. 0 = aucun délai.</div></div>@endif
        </div>
        <label>Paliers de pourcentage (à partir de ... $)</label>
        @for($i = 0; $i < 4; $i++)
            @php($t = $c->tiers[$i] ?? null)
            <div class="tiers" style="grid-template-columns:1fr 1fr">
                <input type="number" step="0.01" name="tiers[{{ $i }}][min_amount]" value="{{ $t ? $t->min_amount + 0 : '' }}" placeholder="à partir de ($)" aria-label="À partir de">
                <input type="number" step="0.01" name="tiers[{{ $i }}][percent]" value="{{ $t ? $t->percent + 0 : '' }}" placeholder="%" aria-label="Pourcentage">
            </div>
        @endfor
        <div class="xs mut" style="margin-top:6px">Colonne gauche : montant de départ du palier · colonne droite : pourcentage.</div>
        <button class="btn" style="margin-top:14px">Enregistrer</button>
    </form>
@endforeach
</div>
@endsection

