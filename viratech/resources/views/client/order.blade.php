@extends('layouts.app')
@section('title', 'Commande '.$order->reference)
@section('own-errors', '1')
@section('heading')
    <div class="mut sm"><a href="/commandes">← Historique</a></div>
    <h1>Commande {{ $order->reference }}</h1>
@endsection
@section('actions')<span class="pill {{ ['completed' => 'ok', 'active' => 'wait'][$order->status] ?? 'bad' }}">{{ $order->statusLabel() }}</span>@endsection

@section('content')
@php($c = $order->corridor)
@php($cur = $order->isActive() ? $order->currentStep() : null)
<div class="grid g7">
    <div class="grid" style="align-content:start">
        <div class="card">
            <div class="row" style="margin-bottom:12px"><x-chan :kind="$c->source_kind" /><span class="mut">→</span><x-chan :kind="$c->target_kind" /><b>{{ $c->label }}</b></div>
            <div class="net"><div class="xs mut">Vous recevrez</div><div class="v num">{{ number_format($order->net_amount, 2, ',', ' ') }} $</div>
                <div class="xs mut">sur {{ \App\Models\PayoutMethod::KINDS[$order->payout_kind] ?? $order->payout_kind }} · {{ $order->payout_holder }} · {{ $order->payout_account }}</div></div>
            <div style="margin-top:12px">
                <div class="kv"><span class="mut">Montant envoyé</span><b class="num">{{ number_format($order->amount, 2, ',', ' ') }} $</b></div>
                <div class="kv"><span class="mut">Frais ({{ $order->percent_applied + 0 }} %)</span><b class="num">− {{ number_format($order->percent_fee, 2, ',', ' ') }} $</b></div>
                @if($order->fixed_fee > 0)<div class="kv"><span class="mut">Frais fixes</span><b class="num">− {{ number_format($order->fixed_fee, 2, ',', ' ') }} $</b></div>@endif
                <div class="kv"><span class="mut">Délai habituel</span><b>{{ $c->etaLabel() }}</b></div>
            </div>
        </div>

        @if($order->isActive())
        <div class="card">
            <div class="b" style="margin-bottom:8px">{{ $c->isWithdrawal() ? 'Payer sur PayPal' : 'Envoyer l\'argent' }}</div>

            @if($c->isWithdrawal() && $cur?->key === 'payment_received')
                @if($order->deposit_mode === 'invoice')
                    <p class="sm">Une facture PayPal de <b>{{ number_format($order->amount, 2, ',', ' ') }} $</b> est prête (référence <span class="mono">{{ $order->paypal_invoice_id }}</span>). Payez-la : votre paiement est détecté automatiquement.</p>
                    @if($simulate)
                        <form method="post" action="/commandes/{{ $order->reference }}/simuler-paiement" style="margin-top:12px">@csrf
                            <button class="btn sec">Simuler le paiement de la facture (mode local)</button>
                        </form>
                    @else
                        <a class="btn" style="margin-top:12px" href="#">Payer la facture PayPal</a>
                    @endif
                @else
                    <p class="sm">Envoyez exactement <b>{{ number_format($order->amount, 2, ',', ' ') }} $</b> en « Biens et services » à :</p>
                    <div class="copy mono" style="margin:10px 0">{{ $paypalAccount?->account_value }}</div>
                    <p class="sm">Dans la note, indiquez la référence <b class="mono">{{ $order->reference }}</b>. Puis signalez votre paiement :</p>
                @endif
                @if($order->deposit_mode === 'account' || ! $simulate)
                    <form method="post" action="/commandes/{{ $order->reference }}/preuve" enctype="multipart/form-data">@csrf
                        <label>Identifiant de transaction PayPal</label><input name="reference_code" placeholder="ex. 7TX48920...">
                        <label>Capture d'écran (facultatif)</label><input type="file" name="file" accept="image/*,.pdf">
                        @error('reference_code')<div class="err">{{ $message }}</div>@enderror
                        <button class="btn" style="margin-top:12px">J'ai payé</button>
                    </form>
                @endif
            @elseif(! $c->isWithdrawal() && $cur?->key === 'deposit_proof')
                <p class="sm">Envoyez exactement <b>{{ number_format($order->amount, 2, ',', ' ') }} $</b> depuis votre compte <b>{{ \App\Models\PayoutMethod::KINDS[$order->source_kind] ?? $order->source_kind }}</b> vers :</p>
                <div class="copy mono" style="margin:10px 0">{{ $depositAccount?->account_value }}</div>
                <p class="sm">Puis envoyez la preuve ci-dessous. Référence à indiquer : <b class="mono">{{ $order->reference }}</b>.</p>
                <form method="post" action="/commandes/{{ $order->reference }}/preuve" enctype="multipart/form-data">@csrf
                    <label>Référence de la transaction</label><input name="reference_code" placeholder="ex. identifiant reçu par SMS">
                    <label>Capture d'écran (facultatif)</label><input type="file" name="file" accept="image/*,.pdf">
                    @error('reference_code')<div class="err">{{ $message }}</div>@enderror
                    <button class="btn" style="margin-top:12px">Envoyer ma preuve</button>
                </form>
            @else
                <p class="sm mut">Rien à faire de votre côté pour le moment. Vous êtes prévenu à chaque étape (notification et email).</p>
            @endif
            @if($order->fees_locked_until?->isFuture())<div class="xs mut" style="margin-top:12px">🔒 Frais garantis jusqu'à {{ $order->fees_locked_until->format('H:i') }}.</div>@endif
        </div>
        @elseif($order->closed_reason)
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
                <div class="kv sm"><span>{{ $p->kind === 'operator_payout' ? 'Preuve de versement' : 'Ma preuve' }} @if($p->reference)<span class="mono">· {{ $p->reference }}</span>@endif</span>
                    @if($p->path)<a href="/commandes/{{ $order->reference }}/preuves/{{ $p->id }}" target="_blank" class="b" style="color:var(--pri)">Voir</a>@endif</div>
            @endforeach
        @endif
    </div>
</div>
@endsection
