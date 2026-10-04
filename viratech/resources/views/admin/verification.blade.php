@extends('layouts.app')
@section('title', 'Vérification '.$s->user->name)
@section('own-errors', '1')
@section('heading')<div class="mut sm"><a href="/admin/verifications">← Vérifications</a></div><h1>{{ $s->user->name }}</h1>@endsection
@section('actions')<span class="pill {{ ['approved' => 'ok', 'pending' => 'wait'][$s->status] ?? 'bad' }}">{{ $s->statusLabel() }}</span>@endsection

@section('content')
@if($errors->any())<div class="flash bad">{{ $errors->first() }}</div>@endif
@if($s->flags)<div class="flash bad">⚠ Alertes automatiques :<ul style="margin:6px 0 0 18px">@foreach($s->flags as $f)<li>{{ $f }}</li>@endforeach</ul></div>@endif
@if($others->isNotEmpty())<div class="flash bad">⚠ Photos identiques trouvées sur d'autres comptes : {{ $others->map(fn ($o) => $o->user->name)->unique()->implode(', ') }}</div>@endif
<div class="grid g2">
    <div class="card">
        <div class="b">Code de sécurité attendu sur la feuille</div>
        <div class="num" style="font-size:44px;font-weight:800;letter-spacing:.2em;color:var(--pri)">{{ $s->challenge_code }}</div>
        <div class="xs mut">Le selfie doit montrer ce code écrit à la main, le visage du client et sa pièce dans la main droite.</div>
        <div class="b" style="margin:16px 0 6px">Selfie</div>
        <img src="/admin/verifications/{{ $s->id }}/fichier/selfie" alt="Selfie" style="width:100%;border-radius:16px;border:1px solid var(--line)">
    </div>
    <div class="card">
        <div class="kv"><span class="mut">Email</span><b>{{ $s->user->email }} {!! $s->user->email_verified_at ? '<span class="pill ok xs">vérifié</span>' : '<span class="pill bad xs">non vérifié</span>' !!}</b></div>
        <div class="kv"><span class="mut">Téléphone</span><b>{{ $s->user->phone ?: '—' }}</b></div>
        <div class="kv"><span class="mut">Nom du compte</span><b>{{ $s->user->name }}</b></div>
        <div class="kv"><span class="mut">Pièce</span><b>{{ $s->idTypeLabel() }}</b></div>
        <div class="kv"><span class="mut">Envoyé le</span><b>{{ $s->created_at->format('d/m/Y H:i') }}</b></div>
        <div class="kv"><span class="mut">Échanges terminés</span><b>{{ $s->user->orders()->where('status', 'completed')->count() }}</b></div>
        <div class="b" style="margin:16px 0 6px">Pièce d'identité (avant)</div>
        <img src="/admin/verifications/{{ $s->id }}/fichier/front" alt="Pièce" style="width:100%;border-radius:16px;border:1px solid var(--line)">
        @if($s->id_back_path)<div class="b" style="margin:16px 0 6px">Pièce d'identité (arrière)</div><img src="/admin/verifications/{{ $s->id }}/fichier/back" alt="Pièce arrière" style="width:100%;border-radius:16px;border:1px solid var(--line)">@endif
    </div>
</div>
@if($s->status === 'pending')
<div class="card" style="margin-top:18px">
    <div class="grid g2">
        <form method="post" action="/admin/verifications/{{ $s->id }}/approuver">@csrf
            <div class="b">Tout correspond</div><p class="xs mut">Le client passe au niveau « identité vérifiée » et son plafond augmente.</p>
            <button class="btn" style="margin-top:8px">✓ Approuver l'identité</button></form>
        <form method="post" action="/admin/verifications/{{ $s->id }}/refuser">@csrf
            <div class="b">Refuser</div>
            <input name="reason" placeholder="Raison montrée au client (ex. photo floue, code absent)" required>
            <button class="btn red" style="margin-top:8px">Refuser le dossier</button></form>
    </div>
</div>
@elseif($s->rejection_reason)<div class="flash bad">Refusé : {{ $s->rejection_reason }}</div>@endif
@endsection