@extends('layouts.app')
@section('title', 'Moyens de réception')
@section('heading')<h1>Moyens de réception</h1><div class="mut sm">Où voulez-vous recevoir votre argent ? Le nom doit être le vôtre.</div>@endsection

@section('content')
<div class="grid g2">
    <div class="card">
        <div class="b">Mes moyens</div>
        <div class="list" style="margin-top:8px">
            @forelse($methods as $m)
                <div>
                    <x-chan :kind="$m->kind" />
                    <div style="flex:1"><b>{{ $m->kindLabel() }}@if($m->label) · {{ $m->label }}@endif</b><div class="xs mut">{{ $m->holder_name }} · {{ $m->masked() }}</div></div>
                    <span class="pill {{ $m->is_verified ? 'ok' : 'wait' }} xs">{{ $m->is_verified ? 'Vérifié' : 'À vérifier' }}</span>
                    <form method="post" action="/moyens-de-reception/{{ $m->id }}" onsubmit="return confirm('Supprimer ce moyen ?')">@csrf @method('DELETE')<button class="btn ghost small">Supprimer</button></form>
                </div>
            @empty
                <div class="empty">Aucun moyen enregistré. Ajoutez le premier.</div>
            @endforelse
        </div>
    </div>
    <div class="card">
        <div class="b">Ajouter un moyen</div>
        <form method="post" action="/moyens-de-reception">@csrf
            <label>Type</label>
            <select name="kind">@foreach(\App\Models\PayoutMethod::KINDS as $k => $l)<option value="{{ $k }}" @selected(old('kind') === $k)>{{ $l }}</option>@endforeach</select>
            <label>Numéro de compte / de téléphone / email PayPal</label><input name="account_value" value="{{ old('account_value') }}" required>
            @error('account_value')<div class="err">{{ $message }}</div>@enderror
            <label>Nom du titulaire</label><input name="holder_name" value="{{ old('holder_name', auth()->user()->name) }}" required>
            <label>Nom court (facultatif)</label><input name="label" value="{{ old('label') }}" placeholder="ex. Mon Equity USD">
            <button class="btn" style="margin-top:16px">Ajouter</button>
        </form>
    </div>
</div>
@endsection
