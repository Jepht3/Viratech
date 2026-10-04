@extends('layouts.app')
@section('title', 'Commande '.$order->reference)
@section('own-errors', '1')
@section('heading')
    <div class="mut sm"><a href="/admin">← File de validation</a></div>
    <h1>Commande {{ $order->reference }}</h1>
@endsection
@section('actions')<span class="pill {{ ['completed' => 'ok', 'active' => 'wait'][$order->status] ?? 'bad' }}">{{ $order->statusLabel() }}</span>@endsection

@section('content')
@php($c = $order->corridor)
@php($cur = $order->isActive() ? $order->currentStep() : null)
@php($def = $cur ? \App\Services\OrderSteps::definition($c->isWithdrawal(), $cur->key) : null)
@if($errors->any())<div class="flash bad">{{ $errors->first() }}</div>@endif
<div class="grid g7">
    <div class="grid" style="align-content:start">
        <div class="card">
            <div class="row" style="margin-bottom:12px"><x-chan :kind="$c->source_kind" /><span class="mut">→</span><x-chan :kind="$c->target_kind" /><b>{{ $c->label }}</b></div>
            <div class="kv"><span class="mut">Client</span><b>{{ $order->user->name }} · N{{ $order->user->kyc_level }} · {{ $order->user->phone }}</b></div>
            <div class="kv"><span class="mut">Montant reçu / à recevoir</span><b class="num">{{ number_format($order->amount, 2, ',', ' ') }} $</b></div>
            <div class="kv"><span class="mut">Frais {{ $order->percent_applied + 0 }} %@if($order->fixed_fee > 0) + {{ $order->fixed_fee + 0 }} $ fixes @endif</span><b class="num">− {{ number_format($order->total_fee, 2, ',', ' ') }} $</b></div>
            <div class="kv"><span class="mut">À verser</span><b class="num" style="color:var(--pri);font-size:20px">{{ number_format($order->net_amount, 2, ',', ' ') }} $</b></div>
            <div class="kv"><span class="mut">Destination</span><b>{{ \App\Models\PayoutMethod::KINDS[$order->payout_kind] ?? $order->payout_kind }} · <span class="mono">{{ $order->payout_account }}</span></b></div>
            <div class="kv"><span class="mut">Titulaire (doit correspondre au nom du client)</span><b>{{ $order->payout_holder }} @if(strcasecmp(trim($order->payout_holder), trim($order->user->name)) === 0)<span class="pill ok xs">Nom ✓</span>@else<span class="pill bad xs">Nom ≠ client</span>@endif</b></div>
            @if($order->source_kind && ! $c->isWithdrawal())<div class="kv"><span class="mut">Dépôt depuis</span><b>{{ $order->source_kind }}</b></div>@endif
            @if($order->paypal_invoice_id)<div class="kv"><span class="mut">Facture PayPal</span><b class="mono">{{ $order->paypal_invoice_id }}</b></div>@endif
        </div>

        @if($cur)
        <div class="card">
            <div class="b">Étape en cours : {{ $cur->pending_label }}</div>
            @if($cur->status === 'blocked')
                <div class="flash bad" style="margin-top:10px">Bloquée : {{ $cur->note }}</div>
                <form method="post" action="/admin/commandes/{{ $order->reference }}/debloquer">@csrf<button class="btn">Lever le blocage</button></form>
            @elseif($cur->actor === 'client')
                <p class="sm mut" style="margin-top:8px">En attente du client. Rien à faire pour le moment.</p>
            @else
                <form method="post" action="/admin/commandes/{{ $order->reference }}/etape" enctype="multipart/form-data">@csrf
                    <input type="hidden" name="key" value="{{ $cur->key }}">
                    @if(($def['needs'] ?? null) === 'reference')
                        <label>{{ $cur->key === 'paypal_sent' ? 'Identifiant de la transaction PayPal envoyée' : 'Référence de la transaction (numéro de reçu)' }}</label>
                        <input name="reference_code" required>
                        <label>Capture de la preuve (facultatif)</label><input type="file" name="file" accept="image/*,.pdf">
                    @endif
                    <button class="btn" style="margin-top:14px">✓ Valider : {{ $cur->label }}</button>
                    @if($cur->key === 'payment_received')<div class="xs mut" style="margin-top:6px">Confirmez uniquement si le paiement est bien visible sur le compte PayPal Business.</div>@endif
                </form>
            @endif

            <div class="grid g2" style="margin-top:16px">
                <form method="post" action="/admin/commandes/{{ $order->reference }}/bloquer">@csrf
                    <label style="margin-top:0">Bloquer avec une raison (le client la voit)</label><input name="reason" placeholder="ex. Capture illisible, merci de renvoyer" required>
                    <button class="btn ghost small" style="margin-top:8px">Bloquer</button></form>
                <form method="post" action="/admin/commandes/{{ $order->reference }}/refuser" onsubmit="return confirm('Refuser définitivement cette commande ?')">@csrf
                    <label style="margin-top:0">Refuser la commande</label><input name="reason" placeholder="Raison du refus" required>
                    <button class="btn red small" style="margin-top:8px">Refuser</button></form>
            </div>
        </div>
        @elseif($order->closed_reason)
            <div class="flash bad">{{ $order->closed_reason }}</div>
        @endif
    </div>

    <div class="grid" style="align-content:start">
        <div class="card"><div class="b" style="margin-bottom:10px">Étapes réelles</div>@include('partials.steps', ['order' => $order])</div>
        <div class="card"><div class="b">Preuves</div>
            @forelse($order->proofs as $p)
                <div class="kv sm"><span>{{ $p->kind === 'operator_payout' ? 'Versement' : 'Client' }} · <span class="mono">{{ $p->reference ?: '—' }}</span> <span class="mut">{{ $p->created_at->format('d/m H:i') }}</span></span>
                    @if($p->path)<a href="/commandes/{{ $order->reference }}/preuves/{{ $p->id }}" target="_blank" class="b" style="color:var(--pri)">Voir</a>@endif</div>
            @empty<div class="empty sm">Aucune preuve.</div>@endforelse
        </div>
    </div>
</div>
@endsection
