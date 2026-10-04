@extends('layouts.app')
@section('title', 'Vérifications')
@section('heading')<h1>Vérifications d'identité</h1><div class="mut sm">Comparez le visage, la pièce et le code écrit à la main avant d'approuver.</div>@endsection

@section('content')
<div class="card">
    <div class="b">À traiter ({{ $pending->count() }})</div>
    @forelse($pending as $s)
        <a class="obs" href="/admin/verifications/{{ $s->id }}" style="margin-top:10px">
            <div class="avatar" style="margin:0;width:44px;height:44px;font-size:15px;background:var(--navy);color:#fff;border-width:2px">{{ $s->user->initials() }}</div>
            <div style="flex:1"><b class="sm">{{ $s->user->name }}</b><div class="xs mut">{{ $s->idTypeLabel() }} · envoyé {{ $s->created_at->diffForHumans() }} · {{ $s->user->phone }}</div></div>
            @if($s->flags)<span class="pill bad xs">⚠ {{ count($s->flags) }} alerte(s)</span>@endif
        </a>
    @empty
        <div class="empty">Aucun dossier à traiter. 🎉</div>
    @endforelse
</div>
<div class="card" style="margin-top:18px">
    <div class="b">Derniers dossiers traités</div>
    <table style="margin-top:8px"><tr><th>Client</th><th>Pièce</th><th>Résultat</th><th>Par</th><th>Date</th></tr>
        @forelse($done as $s)
            <tr class="hover" onclick="location='/admin/verifications/{{ $s->id }}'" style="cursor:pointer"><td>{{ $s->user->name }}</td><td>{{ $s->idTypeLabel() }}</td><td><span class="pill {{ $s->status === 'approved' ? 'ok' : 'bad' }}">{{ $s->statusLabel() }}</span></td><td>{{ $s->reviewer?->name }}</td><td class="mut">{{ $s->reviewed_at?->format('d/m H:i') }}</td></tr>
        @empty<tr><td colspan="5" class="empty">Rien pour le moment.</td></tr>@endforelse
    </table>
</div>
@endsection