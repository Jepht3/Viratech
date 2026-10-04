@extends('layouts.app')
@section('title', "Journal d'audit")
@section('heading')<h1>Journal d'audit</h1><div class="mut sm">Qui a fait quoi, quand. Non modifiable.</div>@endsection

@section('content')
<div class="card">
    <table>
        <tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Objet</th><th>Détail</th></tr>
        @foreach($logs as $l)
            <tr><td class="mut">{{ $l->created_at?->format('d/m H:i:s') }}</td><td>{{ \App\Models\User::find($l->user_id)?->name ?? 'Système' }}</td><td class="b">{{ $l->action }}</td><td>{{ $l->subject_type }} #{{ $l->subject_id }}</td><td class="xs mut mono">{{ json_encode($l->new, JSON_UNESCAPED_UNICODE) }}</td></tr>
        @endforeach
    </table>
    <div style="margin-top:12px">{{ $logs->links() }}</div>
</div>
@endsection
