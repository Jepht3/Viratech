@extends('layouts.app')
@section('title', 'Commandes')
@section('heading')<h1>Toutes les commandes</h1>@endsection
@section('actions')
    @foreach([null => 'Toutes', 'active' => 'En cours', 'completed' => 'Terminées', 'rejected' => 'Refusées', 'expired' => 'Expirées'] as $k => $l)
        <a class="pill {{ ($statut ?? null) === $k ? 'ok' : 'info' }}" href="/admin/commandes{{ $k ? '?statut='.$k : '' }}">{{ $l }}</a>
    @endforeach
@endsection

@section('content')
<div class="card">
    <table>
        <tr><th>Commande</th><th>Échange</th><th>Client</th><th>Date</th><th>Envoyé</th><th>Frais</th><th>Net</th><th>Statut</th></tr>
        @forelse($orders as $o)
            <tr class="hover" onclick="location='/admin/commandes/{{ $o->reference }}'" style="cursor:pointer">
                <td class="b">{{ $o->reference }}</td><td>{{ $o->corridor->label }}</td><td>{{ $o->user->name }}</td>
                <td class="mut">{{ $o->created_at->format('d/m H:i') }}</td>
                <td class="num">{{ number_format($o->amount, 2, ',', ' ') }} $</td><td class="num">{{ number_format($o->total_fee, 2, ',', ' ') }} $</td><td class="num b">{{ number_format($o->net_amount, 2, ',', ' ') }} $</td>
                <td><span class="pill {{ ['completed' => 'ok', 'active' => 'wait'][$o->status] ?? 'bad' }}">{{ $o->statusLabel() }}</span></td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">Aucune commande.</td></tr>
        @endforelse
    </table>
    <div style="margin-top:12px">{{ $orders->links() }}</div>
</div>
@endsection
