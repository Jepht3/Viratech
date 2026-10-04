@extends('layouts.app')
@section('title', 'Comptes de réception')
@section('heading')<h1>Comptes de réception</h1><div class="mut sm">Les comptes affichés aux clients quand ils doivent payer. Changements journalisés.</div>@endsection

@section('content')
<div class="grid g3">
@foreach($accounts as $a)
    <form method="post" action="/admin/comptes/{{ $a->id }}" class="card">@csrf
        <div class="row"><x-chan :kind="$a->kind" /><b>{{ $a->label }}</b></div>
        <label>Libellé</label><input name="label" value="{{ $a->label }}" required>
        <label>{{ $a->kind === 'paypal' ? 'Adresse PayPal Business' : 'Numéro de compte' }}</label><input name="account_value" value="{{ $a->account_value }}" required>
        <label>Titulaire (facultatif)</label><input name="holder_name" value="{{ $a->holder_name }}">
        <label class="row" style="font-weight:500"><input type="checkbox" name="is_active" value="1" style="width:auto" @checked($a->is_active)> Actif</label>
        @if(str_starts_with($a->account_value, '000'))<div class="flash bad" style="margin-top:10px">Valeur provisoire : à remplacer avant la mise en service.</div>@endif
        <button class="btn" style="margin-top:12px">Enregistrer</button>
    </form>
@endforeach
</div>
@endsection
