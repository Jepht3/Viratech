@extends('layouts.app')
@section('title', 'Historique')
@section('heading')<h1>Historique</h1>@endsection

@section('content')
<div class="card">
    <table>
        <tr><th>Commande</th><th>Échange</th><th>Date</th><th>Envoyé</th><th>Net</th><th>Statut</th></tr>
        @forelse($orders as $o)
            <tr class="hover" onclick="location='/commandes/{{ $o->reference }}'" style="cursor:pointer">
                <td class="b">{{ $o->reference }}</td>
                <td><div class="row"><x-chan :kind="$o->corridor->source_kind" :small="true" /><span class="mut">→</span><x-chan :kind="$o->corridor->target_kind" :small="true" /> {{ $o->corridor->label }}</div></td>
                <td class="mut">{{ $o->created_at->format('d/m/Y H:i') }}</td>
                <td class="num">{{ number_format($o->amount, 2, ',', ' ') }} $</td>
                <td class="num b">{{ number_format($o->net_amount, 2, ',', ' ') }} $</td>
                <td><span class="pill {{ ['completed' => 'ok', 'active' => 'wait'][$o->status] ?? 'bad' }}">{{ $o->statusLabel() }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">Aucune commande pour le moment.</td></tr>
        @endforelse
    </table>
    <div style="margin-top:12px">{{ $orders->links() }}</div>
</div>
@endsection
