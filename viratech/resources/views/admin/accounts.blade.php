@extends('layouts.app')
@section('title', 'Comptes de réception')
@section('heading')<h1>Comptes de réception</h1><div class="mut sm">Un numéro par réseau (M-Pesa, Airtel, Orange, Afrimoney, Equity, PayPal). Changements journalisés.</div>@endsection

@section('content')
@php($fp = \App\Models\Setting::bool('flexpay.enabled'))
@if($fp)
    <div class="flash row" style="background:var(--okbg);color:var(--okfg);gap:12px"><x-chan kind="flexpay" /><span>FlexPay est <b>activé</b> : les clients ne voient plus ces numéros pour payer. Ils arrivent directement sur l'écran de paiement automatique FlexPay (mobile money ou carte Visa). Ces numéros servent de secours, pour PayPal, et si vous désactivez FlexPay.</span></div>
@else
    <div class="flash" style="background:var(--waitbg);color:var(--waitfg)">FlexPay est désactivé : le client voit le numéro du réseau qu'il a choisi (M-Pesa vers le numéro M-Pesa, Airtel vers le numéro Airtel…) et envoie la capture de son paiement.</div>
@endif
<div class="grid g3">
@foreach($accounts as $a)
    <form method="post" action="/admin/comptes/{{ $a->id }}" class="card">@csrf
        <div class="row"><x-chan :kind="$a->kind" /><b>{{ $a->kindLabel() }}</b></div>
        <label>Libellé</label><input name="label" value="{{ $a->label }}" required>
        <label>{{ $a->kind === 'paypal' ? 'Adresse PayPal Business' : 'Numéro / compte' }}</label><input name="account_value" value="{{ $a->account_value }}" required>
        <label>Titulaire (facultatif)</label><input name="holder_name" value="{{ $a->holder_name }}">
        <label class="row" style="font-weight:500"><input type="checkbox" name="is_active" value="1" style="width:auto" @checked($a->is_active)> Actif</label>
        @if(str_starts_with($a->account_value, '000'))<div class="flash bad" style="margin-top:10px">Valeur provisoire : à remplacer avant la mise en service.</div>@endif
        <div class="row" style="margin-top:12px"><button class="btn">Enregistrer</button>
            <button class="btn red small" type="submit" form="del{{ $a->id }}" onclick="return confirm('Supprimer ce numéro ?')">Supprimer</button></div>
    </form>
    <form id="del{{ $a->id }}" method="post" action="/admin/comptes/{{ $a->id }}" hidden>@csrf @method('DELETE')</form>
@endforeach
    <form method="post" action="/admin/comptes" class="card" style="border:2px dashed var(--line);box-shadow:none">@csrf
        <div class="b">+ Ajouter un numéro</div>
        <label>Réseau</label><select name="kind">@foreach(\App\Models\CompanyAccount::KINDS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
        <label>Numéro / compte</label><input name="account_value" required placeholder="ex. 0812345678">
        <label>Libellé (facultatif)</label><input name="label">
        <label>Titulaire (facultatif)</label><input name="holder_name">
        <button class="btn" style="margin-top:12px">Ajouter</button>
    </form>
</div>
@endsection