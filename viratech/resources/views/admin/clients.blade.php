@extends('layouts.app')
@section('title', 'Clients')
@section('heading')<h1>Clients et vérification</h1>@endsection

@section('content')
<div class="card">
    <table>
        <tr><th>Client</th><th>Téléphone</th><th>Commandes</th><th>Inscrit le</th><th>Niveau de vérification</th></tr>
        @forelse($clients as $cl)
            <tr><td><b>{{ $cl->name }}</b><div class="xs mut">{{ $cl->email }}</div></td><td>{{ $cl->phone }}</td><td>{{ $cl->orders_count }}</td><td class="mut">{{ $cl->created_at->format('d/m/Y') }}</td>
                <td><form method="post" action="/admin/clients/{{ $cl->id }}/kyc" class="row" style="gap:8px">@csrf
                    <select name="kyc_level" style="width:auto;padding:8px 12px">@foreach([0 => 'N0 · email non vérifié (0 $)', 1 => 'N1 · email vérifié (150 $/mois)', 2 => 'N2 · identité vérifiée (3 000 $/mois)', 3 => 'N3 · sur mesure (10 000 $/mois)'] as $k => $l)<option value="{{ $k }}" @selected($cl->kyc_level === $k)>{{ $l }}</option>@endforeach</select>
                    <button class="btn ghost small">OK</button></form></td></tr>
        @empty
            <tr><td colspan="5" class="empty">Aucun client.</td></tr>
        @endforelse
    </table>
    <div style="margin-top:12px">{{ $clients->links() }}</div>
</div>
@endsection
