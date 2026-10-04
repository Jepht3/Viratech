@extends('layouts.app')
@section('title', 'Mon profil')
@section('own-errors', '1')
@section('heading')<h1>Mon profil</h1><div class="mut sm">Photo, téléphone et vérification d'identité.</div>@endsection

@section('content')
@if($errors->any())<div class="flash bad">{{ $errors->first() }}</div>@endif
@if(session('error'))<div class="flash bad">{{ session('error') }}</div>@endif
<div class="grid g2">
    <div class="grid" style="align-content:start">
        <div class="card">
            <div class="b">Photo de profil</div>
            <div class="row" style="margin-top:14px;gap:18px">
                <div class="avatar" style="margin:0;width:84px;height:84px;font-size:28px;overflow:hidden;background:var(--navy);color:#fff;border-color:var(--card);box-shadow:0 0 0 2px var(--line)">
                    @if($user->avatar_path)<img src="/avatar/{{ $user->id }}?v={{ $user->updated_at->timestamp }}" alt="" style="width:100%;height:100%;object-fit:cover">@else{{ $user->initials() }}@endif
                </div>
                <form method="post" action="/profil/photo" enctype="multipart/form-data" style="flex:1">@csrf
                    <input type="file" name="photo" accept="image/*" capture="user" required>
                    <button class="btn small" style="margin-top:10px">Enregistrer la photo</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="row sp"><div class="b">Téléphone</div>
                @if($user->phone_verified_at)<span class="pill ok">✓ Vérifié</span>@else<span class="pill wait">À vérifier</span>@endif</div>
            @if($user->phone_verified_at)
                <p class="sm mut" style="margin-top:8px">{{ $user->phone }} · vérifié le {{ $user->phone_verified_at->format('d/m/Y') }}.</p>
            @else
                <p class="sm mut" style="margin-top:8px">Un code est envoyé par SMS. La vérification du téléphone est obligatoire avant tout échange.</p>
                <form method="post" action="/profil/telephone/code">@csrf
                    <label>Numéro de téléphone</label><input name="phone" value="{{ old('phone', $user->phone) }}" placeholder="+243 ..." required>
                    <button class="btn small ghost" style="margin-top:10px">Envoyer le code</button></form>
                <form method="post" action="/profil/telephone/verifier">@csrf
                    <label>Code reçu par SMS</label><input name="code" inputmode="numeric" maxlength="6" placeholder="123456" required>
                    <button class="btn small" style="margin-top:10px">Vérifier</button></form>
            @endif
        </div>

        @if($limit)
        <div class="card">
            <div class="b">Mon plafond mensuel</div>
            <div class="num" style="font-size:32px;font-weight:800;margin-top:6px">{{ number_format($limit['limit'], 0, ',', ' ') }} $</div>
            <div class="xs mut">{{ $limit['custom'] ? 'Plafond fixé par Viratech' : 'Niveau '.$limit['level'].($limit['multiplier'] > 1 ? ' · bonus ×'.$limit['multiplier'].' grâce à vos échanges réussis' : '') }}</div>
            @if($limit['next'])<div class="flash" style="background:var(--pri2);color:var(--pri);margin-top:12px">Encore {{ $limit['next']['orders_needed'] }} échange(s) réussi(s) et votre plafond passe à {{ number_format($limit['next']['limit'], 0, ',', ' ') }} $.</div>@endif
            <div class="xs mut" style="margin-top:10px">Téléphone vérifié : {{ number_format(config('viratech.limits')[1], 0, ',', ' ') }} $/mois · Identité vérifiée : {{ number_format(config('viratech.limits')[2], 0, ',', ' ') }} $/mois. La limite augmente aussi avec le nombre d'échanges terminés.</div>
        </div>
        @endif
    </div>

    <div class="card" style="align-self:start">
        <div class="row sp"><div class="b">Vérification d'identité</div>
            @if((int) $user->kyc_level >= 2)<span class="pill ok">✓ Vérifiée</span>
            @elseif($submission?->status === 'pending')<span class="pill wait">En cours de vérification</span>
            @elseif($submission?->status === 'rejected')<span class="pill bad">Refusée</span>
            @else<span class="pill info">Non vérifiée</span>@endif</div>

        @if($user->role !== 'client')
            <p class="sm mut" style="margin-top:8px">Compte équipe : pas de vérification nécessaire.</p>
        @elseif((int) $user->kyc_level >= 2)
            <p class="sm mut" style="margin-top:8px">Votre identité est vérifiée. Votre plafond mensuel est plus élevé.</p>
        @elseif($submission?->status === 'pending')
            <p class="sm mut" style="margin-top:8px">Votre dossier a été envoyé le {{ $submission->created_at->format('d/m/Y H:i') }}. Nous vous prévenons dès qu'il est vérifié.</p>
        @elseif(! $user->phone_verified_at)
            <p class="sm mut" style="margin-top:8px">Vérifiez d'abord votre numéro de téléphone.</p>
        @else
            @if($submission?->status === 'rejected')<div class="flash bad" style="margin-top:10px">Dossier refusé : {{ $submission->rejection_reason }}</div>@endif
            <p class="sm mut" style="margin-top:8px">Pour protéger votre compte, nous vérifions que c'est bien vous. Il faut 2 photos prises <b>maintenant</b> avec votre téléphone.</p>
            <div class="copy" style="margin:12px 0;text-align:center">
                <div class="xs mut">Écrivez ce code sur une feuille et tenez-la sur la photo</div>
                <div class="num" style="font-size:36px;font-weight:800;letter-spacing:.2em;color:var(--pri)">{{ $challenge?->code }}</div>
                <div class="xs mut">valable jusqu'à {{ $challenge?->expires_at->format('H:i') }}</div>
                <form method="post" action="/profil/verification/code">@csrf<button class="btn ghost small" style="margin-top:8px">Nouveau code</button></form>
            </div>
            <form method="post" action="/profil/verification" enctype="multipart/form-data">@csrf
                <label>Type de pièce</label><select name="id_type">@foreach($idTypes as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                <label>1. Selfie : votre visage, votre pièce dans la main droite et la feuille avec le code</label>
                <input type="file" name="selfie" accept="image/*" capture="user" required>
                <label>2. Photo de la pièce d'identité (face avant, lisible)</label>
                <input type="file" name="id_front" accept="image/*" capture="environment" required>
                <label>3. Face arrière de la pièce (si elle existe)</label>
                <input type="file" name="id_back" accept="image/*" capture="environment">
                <button class="btn" style="margin-top:14px">Envoyer mon dossier</button>
            </form>
            <p class="xs mut" style="margin-top:10px">Vos photos sont stockées de façon privée et ne servent qu'à vérifier votre identité. Les anciennes photos ou les photos déjà utilisées par un autre compte sont refusées.</p>
        @endif
    </div>
</div>
@endsection