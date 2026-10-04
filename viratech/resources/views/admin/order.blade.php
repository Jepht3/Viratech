@extends('layouts.app')
@section('title', 'Commande '.$order->reference)
@section('own-errors', '1')
@section('heading')
    <div class="mut sm"><a href="/admin">← File de validation</a></div>
    <h1>Commande {{ $order->reference }}</h1>
@endsection
@section('actions')<span class="pill {{ ['completed' => 'ok', 'active' => 'wait'][$order->status] ?? 'bad' }}">{{ $order->statusLabel() }}</span>@endsection

@section('content')
@php
    $c = $order->corridor;
    $cur = $order->isActive() ? $order->currentStep() : null;
    $needsProof = $cur && in_array($cur->key, ['payout_done'], true);
    $hold = $order->payout_not_before?->isFuture();
    $fp = \App\Models\Setting::bool('flexpay.enabled') && \App\Models\Setting::bool('flexpay.payout_enabled');
@endphp
@if($errors->any())<div class="flash bad">{{ $errors->first() }}</div>@endif
<div class="grid g7">
    <div class="grid" style="align-content:start">
        <div class="card">
            <div class="row" style="margin-bottom:12px"><x-chan :kind="$c->source_kind" /><span class="mut">→</span><x-chan :kind="$c->target_kind" /><b>{{ $c->label }}</b></div>
            <div class="kv"><span class="mut">Client</span><b>{{ $order->user->name }} · N{{ $order->user->kyc_level }} · {{ $order->user->phone }}</b></div>
            <div class="kv"><span class="mut">Façon de payer</span><b>{{ ['paypal_invoice' => 'Facture PayPal (automatique)', 'paypal_account' => 'Envoi à notre PayPal + capture', 'transfer' => 'Virement direct + capture', 'flexpay_mobile' => 'Mobile money FlexPay', 'flexpay_card' => 'Carte Visa FlexPay'][$order->payment_method] ?? $order->payment_method }}</b></div>
            <div class="kv"><span class="mut">Montant reçu / à recevoir</span><b class="num">{{ number_format($order->amount, 2, ',', ' ') }} $</b></div>
            <div class="kv"><span class="mut">Frais {{ $order->percent_applied + 0 }} %@if($order->fixed_fee > 0) + {{ $order->fixed_fee + 0 }} $ fixes @endif</span><b class="num">− {{ number_format($order->total_fee, 2, ',', ' ') }} $</b></div>
            <div class="kv"><span class="mut">À verser</span><b class="num" style="color:var(--pri);font-size:20px">{{ number_format($order->net_amount, 2, ',', ' ') }} $</b></div>
            <div class="kv"><span class="mut">Destination</span><b>{{ \App\Models\PayoutMethod::KINDS[$order->payout_kind] ?? $order->payout_kind }} · <span class="mono">{{ $order->payout_account }}</span></b></div>
            <div class="kv"><span class="mut">Titulaire (doit correspondre au nom du client)</span><b>{{ $order->payout_holder }} @if(strcasecmp(trim($order->payout_holder), trim($order->user->name)) === 0)<span class="pill ok xs">Nom ✓</span>@else<span class="pill bad xs">Nom ≠ client</span>@endif</b></div>
            @if($order->source_kind && ! $c->isWithdrawal())<div class="kv"><span class="mut">Dépôt depuis</span><b>{{ $order->source_kind }}</b></div>@endif
            @if($order->paypal_invoice_id)<div class="kv"><span class="mut">Facture PayPal</span><b class="mono">{{ $order->paypal_invoice_id }}</b></div>@endif
            @if($order->flexpay_reference)<div class="kv"><span class="mut">Paiement FlexPay</span><b class="mono">{{ $order->flexpay_reference }}</b></div>@endif
            @if($order->payout_not_before)<div class="kv"><span class="mut">Délai de sécurité</span><b>{{ $hold ? 'jusqu\'au '.$order->payout_not_before->format('d/m/Y H:i') : 'écoulé' }}</b></div>@endif
        </div>

        @if($cur)
        <div class="card">
            <div class="b">Étape en cours : {{ $cur->pending_label }}</div>
            @if($cur->status === 'blocked')
                <div class="flash bad" style="margin-top:10px">Bloquée : {{ $cur->note }}</div>
                <form method="post" action="/admin/commandes/{{ $order->reference }}/debloquer">@csrf<button class="btn">Lever le blocage</button></form>
            @elseif($cur->key === 'client_payment')
                <p class="sm mut" style="margin-top:8px">En attente du paiement du client{{ in_array($order->payment_method, ['paypal_invoice', 'flexpay_mobile', 'flexpay_card']) ? ' (confirmation automatique)' : ' et de sa capture' }}. Rien à faire pour le moment.</p>
                @if($simulate && in_array($order->payment_method, ['paypal_invoice', 'flexpay_mobile', 'flexpay_card']))<div class="xs mut" style="margin-top:6px">Mode local : le client dispose d'un bouton de simulation.</div>@endif
            @elseif(in_array($cur->key, ['security_check', 'payout_in_progress', 'payout_done']) && $hold)
                <div class="flash" style="background:var(--waitbg);color:var(--waitfg);margin-top:10px">⏳ Délai de sécurité jusqu'au <b>{{ $order->payout_not_before->format('d/m/Y H:i') }}</b> : vérifiez qu'aucun litige ou rétrofacturation n'est ouvert avant de verser.</div>
                @if(auth()->user()->isAdmin())
                    <form method="post" action="/admin/commandes/{{ $order->reference }}/lever-delai" onsubmit="return confirm('Lever le délai de sécurité de cette commande ?')">@csrf
                        <label>Lever exceptionnellement le délai (administrateur)</label><input name="reason" placeholder="Raison (journalisée)" required>
                        <button class="btn red small" style="margin-top:8px">Lever le délai</button></form>
                @endif
            @else
                <form method="post" action="/admin/commandes/{{ $order->reference }}/etape" enctype="multipart/form-data">@csrf
                    <input type="hidden" name="key" value="{{ $cur->key }}">
                    @if($needsProof)
                        <label>Capture du versement (obligatoire : elle sera envoyée au client)</label><input type="file" name="file" accept="image/*,.pdf" required>
                        <label>Référence de la transaction (facultatif)</label><input name="reference_code">
                    @endif
                    <button class="btn" style="margin-top:14px">✓ Valider : {{ $cur->label }}</button>
                    @if($cur->key === 'payment_verified')<div class="xs mut" style="margin-top:6px">Confirmez uniquement si l'argent est bien arrivé sur le compte concerné et que le nom du payeur correspond au client.</div>@endif
                </form>
                @if($cur->key === 'payout_done' && $c->target_kind === 'mobile_money' && ! $order->flexpay_payout_reference)
                    <div style="margin-top:16px;border-top:1px solid var(--line);padding-top:12px">
                        <div class="b sm">Ou verser automatiquement par FlexPay</div>
                        @if($fp)
                            <form method="post" action="/admin/commandes/{{ $order->reference }}/versement-flexpay" onsubmit="return confirm('Envoyer {{ number_format($order->net_amount, 2) }} $ à {{ $order->payout_account }} via FlexPay ?')">@csrf
                                <button class="btn sec" style="margin-top:8px">Verser {{ number_format($order->net_amount, 2, ',', ' ') }} $ via FlexPay</button></form>
                        @else
                            <div class="xs mut">Activez le versement FlexPay dans les paramètres pour l'utiliser.</div>
                        @endif
                    </div>
                @endif
            @endif
            @if($order->payout_via === 'flexpay' && $cur->key === 'payout_done')
                <div class="flash ok" style="margin-top:12px">Versement FlexPay lancé (réf. <span class="mono">{{ $order->flexpay_payout_reference }}</span>). Il se confirme automatiquement.</div>
                @if($simulate)<form method="post" action="/admin/commandes/{{ $order->reference }}/simuler-versement">@csrf<button class="btn sec small">Simuler la confirmation FlexPay (mode local)</button></form>@endif
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
        <div class="card"><div class="b">Preuves et captures</div>
            @forelse($order->proofs as $p)
                <div class="kv sm"><span>{{ $p->kind === 'operator_payout' ? 'Versement (Viratech)' : 'Paiement du client' }} · <span class="mono">{{ $p->reference ?: '—' }}</span> <span class="mut">{{ $p->created_at->format('d/m H:i') }}</span></span>
                    @if($p->path)<a href="/commandes/{{ $order->reference }}/preuves/{{ $p->id }}" target="_blank" class="b" style="color:var(--pri)">Voir</a>@endif</div>
            @empty<div class="empty sm">Aucune preuve.</div>@endforelse
        </div>
    </div>
</div>
@endsection
