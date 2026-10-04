@extends('layouts.app')
@section('title', 'Commande '.$order->reference)
@section('own-errors', '1')
@section('heading')
    <div class="mut sm"><a href="/commandes">← Historique</a></div>
    <h1>Commande {{ $order->reference }}</h1>
@endsection
@section('actions')<span class="pill {{ ['completed' => 'ok', 'active' => 'wait'][$order->status] ?? 'bad' }}">{{ $order->statusLabel() }}</span>@endsection

@section('content')
@php
    $c = $order->corridor;
    $cur = $order->isActive() ? $order->currentStep() : null;
    $pm = $order->payment_method;
    $waitingPayment = $cur?->key === 'client_payment';
@endphp
@if($errors->any())<div class="flash bad">{{ $errors->first() }}</div>@endif
<div class="grid g7">
    <div class="grid" style="align-content:start">
        <div class="card">
            <div class="row" style="margin-bottom:12px"><x-chan :kind="$c->source_kind" /><span class="mut">→</span><x-chan :kind="$c->target_kind" /><b>{{ $c->label }}</b></div>
            <div class="net"><div class="xs mut">Vous recevrez</div><div class="v num">{{ number_format($order->net_amount, 2, ',', ' ') }} $</div>
                <div class="xs mut">sur {{ \App\Models\PayoutMethod::KINDS[$order->payout_kind] ?? $order->payout_kind }} · {{ $order->payout_holder }} · {{ $order->payout_account }}</div></div>
            <div style="margin-top:12px">
                <div class="kv"><span class="mut">Montant que vous envoyez</span><b class="num">{{ number_format($order->amount, 2, ',', ' ') }} $</b></div>
                <div class="kv"><span class="mut">Frais ({{ $order->percent_applied + 0 }} %)</span><b class="num">− {{ number_format($order->percent_fee, 2, ',', ' ') }} $</b></div>
                @if($order->fixed_fee > 0)<div class="kv"><span class="mut">Frais fixes</span><b class="num">− {{ number_format($order->fixed_fee, 2, ',', ' ') }} $</b></div>@endif
                <div class="kv"><span class="mut">Délai habituel</span><b>{{ $c->etaLabel() }}</b></div>
                @if($order->payout_not_before?->isFuture())<div class="kv"><span class="mut">Délai de sécurité jusqu'au</span><b>{{ $order->payout_not_before->format('d/m/Y') }}</b></div>@endif
            </div>
        </div>

        @if($order->isActive() && $waitingPayment)
        <div class="card">
            <div class="b row" style="margin-bottom:8px">@if(in_array($pm, ['flexpay_mobile','flexpay_card']))<x-chan kind="flexpay" />@endif{{ in_array($pm, ['flexpay_mobile','flexpay_card']) ? 'Payer avec FlexPay' : ($c->isWithdrawal() ? 'Payer sur PayPal' : 'Payer votre commande') }}</div>

            @if($pm === 'paypal_invoice')
                <p class="sm">Une facture PayPal de <b>{{ number_format($order->amount, 2, ',', ' ') }} $</b> est prête (référence <span class="mono">{{ $order->paypal_invoice_id }}</span>). Payez-la : votre paiement est détecté automatiquement, sans capture à envoyer.</p>
                @if($simulate)
                    <form method="post" action="/commandes/{{ $order->reference }}/simuler-paiement" style="margin-top:12px">@csrf<button class="btn sec">Simuler le paiement de la facture (mode local)</button></form>
                @endif

            @elseif(in_array($pm, ['flexpay_mobile', 'flexpay_card']))
                <p class="sm">{{ $pm === 'flexpay_mobile' ? 'Payez avec votre mobile money : FlexPay vous demande votre numéro, puis vous validez avec votre code sur votre téléphone.' : 'Payez par carte Visa sur la page sécurisée de FlexPay.' }} La confirmation est automatique, sans capture.</p>
                @if($order->flexpay_reference)
                    <div class="flash ok">Demande envoyée (réf. <span class="mono">{{ $order->flexpay_reference }}</span>). Confirmez sur votre téléphone, la commande avance toute seule.</div>
                    @if($order->flexpay_url)<a class="btn" href="{{ $order->flexpay_url }}">Ouvrir la page de paiement</a>@endif
                @else
                    <form method="post" action="/commandes/{{ $order->reference }}/flexpay">@csrf
                        @if($pm === 'flexpay_mobile')<label>Numéro mobile money à débiter</label><input name="phone" value="{{ old('phone', auth()->user()->phone) }}" placeholder="+243 ..." required>@endif
                        <button class="btn" style="margin-top:12px">{{ $pm === 'flexpay_mobile' ? 'Payer avec mobile money' : 'Payer par carte Visa' }}</button>
                    </form>
                @endif
                @if($simulate)
                    <form method="post" action="/commandes/{{ $order->reference }}/simuler-paiement" style="margin-top:12px">@csrf<input type="hidden" name="phone" value="{{ auth()->user()->phone }}"><button class="btn sec">Simuler la confirmation FlexPay (mode local)</button></form>
                @endif

            @else
                @if($pm === 'paypal_account')
                    <p class="sm">Envoyez exactement <b>{{ number_format($order->amount, 2, ',', ' ') }} $</b> en « Biens et services » à :</p>
                    <div class="copy mono" style="margin:10px 0">{{ $paypalAccount?->account_value }}</div>
                    <p class="sm">Dans la note, indiquez la référence <b class="mono">{{ $order->reference }}</b>.</p>
                @else
                    <p class="sm">Envoyez exactement <b>{{ number_format($order->amount, 2, ',', ' ') }} $</b> depuis votre compte <b>{{ \App\Models\PayoutMethod::KINDS[$order->source_kind] ?? $order->source_kind }}</b> vers le numéro <b>{{ $depositAccount?->kindLabel() }}</b> de Viratech :</p>
                    <div class="copy mono" style="margin:10px 0">{{ $depositAccount?->account_value ?? 'Numéro non configuré : contactez Viratech' }}@if($depositAccount?->holder_name)<div class="xs mut">Au nom de {{ $depositAccount->holder_name }}</div>@endif</div>
                    <p class="sm">Référence à indiquer : <b class="mono">{{ $order->reference }}</b>.</p>
                @endif
                <div class="flash" style="background:var(--waitbg);color:var(--waitfg);margin-top:12px">Après votre paiement, envoyez <b>la capture de la transaction</b> : sans elle, nous ne pouvons pas vérifier la réception.</div>
                <form method="post" action="/commandes/{{ $order->reference }}/preuve" enctype="multipart/form-data">@csrf
                    <label>Capture de votre paiement (obligatoire)</label><input type="file" name="file" accept="image/*,.pdf" required>
                    <label>Référence de la transaction (facultatif)</label><input name="reference_code" placeholder="ex. identifiant reçu par SMS ou par PayPal">
                    @error('file')<div class="err">{{ $message }}</div>@enderror
                    <button class="btn" style="margin-top:12px">Envoyer ma preuve de paiement</button>
                </form>
            @endif
            @if($order->fees_locked_until?->isFuture())<div class="xs mut" style="margin-top:12px">🔒 Frais garantis jusqu'à {{ $order->fees_locked_until->format('H:i') }}.</div>@endif
        </div>
        @elseif($order->isActive() && $order->payout_not_before?->isFuture())
        <div class="card"><div class="b">Délai de sécurité</div>
            <p class="sm mut" style="margin-top:6px">Pour vous protéger comme nous protéger des litiges et rétrofacturations PayPal, les fonds issus de PayPal sont gardés jusqu'au <b>{{ $order->payout_not_before->format('d/m/Y') }}</b> avant d'être versés. Votre paiement est bien reçu, rien à faire de votre côté.</p></div>
        @elseif(! $order->isActive() && $order->closed_reason)
            <div class="flash bad">{{ $order->closed_reason }}</div>
        @endif
    </div>

    <div class="card" style="align-self:start">
        <div class="row sp"><div class="b">Où en est ma commande ?</div></div>
        <div class="xs mut" style="margin:2px 0 12px">Étapes réelles, mises à jour à chaque action.</div>
        @include('partials.steps', ['order' => $order])
        @if($order->proofs->isNotEmpty())
            <div class="b sm" style="margin-top:6px">Preuves</div>
            @foreach($order->proofs as $p)
                <div class="kv sm"><span>{{ $p->kind === 'operator_payout' ? 'Preuve du versement (Viratech)' : 'Ma preuve de paiement' }} @if($p->reference)<span class="mono">· {{ $p->reference }}</span>@endif</span>
                    @if($p->path)<a href="/commandes/{{ $order->reference }}/preuves/{{ $p->id }}" target="_blank" class="b" style="color:var(--pri)">Voir la capture</a>@endif</div>
            @endforeach
        @endif
    </div>
</div>
@endsection
