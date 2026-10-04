@extends('layouts.app')
@section('title', 'Mon profil')
@section('own-errors', '1')
@section('heading')<h1>Mon profil</h1><div class="mut sm">Photo, email et vérification d'identité.</div>@endsection

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
            <div class="row sp"><div class="b">Adresse email</div>
                @if($user->email_verified_at)<span class="pill ok">✓ Vérifiée</span>@else<span class="pill wait">À vérifier</span>@endif</div>
            @if($user->email_verified_at)
                <p class="sm mut" style="margin-top:8px">{{ $user->email }} est vérifiée.</p>
            @else
                <p class="sm mut" style="margin-top:8px">Un code à 6 chiffres est envoyé à <b>{{ $user->email }}</b>. La vérification de l'email est obligatoire avant tout échange.</p>
                <form method="post" action="/profil/email/code">@csrf<button class="btn small ghost" style="margin-top:10px">Envoyer le code</button></form>
                <form method="post" action="/profil/email/verifier">@csrf
                    <label>Code reçu par email</label><input name="code" inputmode="numeric" maxlength="6" placeholder="123456" required>
                    @error('email_code')<div class="err">{{ $message }}</div>@enderror
                    <button class="btn small" style="margin-top:10px">Vérifier</button></form>
            @endif
        </div>

        @if($limit)
        <div class="card">
            <div class="b">Mon plafond mensuel</div>
            <div class="num" style="font-size:32px;font-weight:800;margin-top:6px">{{ number_format($limit['limit'], 0, ',', ' ') }} $</div>
            <div class="xs mut">
                @if($limit['custom'])Plafond fixé par Viratech
                @elseif(! $limit['email_verified'])Vérifiez votre email pour pouvoir faire des échanges
                @elseif(! $limit['identity_verified'])Identité non vérifiée : {{ number_format($limit['limit'], 0, ',', ' ') }} $ seulement
                @else Identité vérifiée{{ $limit['multiplier'] > 1 ? ' · bonus ×'.$limit['multiplier'].' grâce à vos échanges réussis' : '' }}@endif
            </div>
            @if($limit['email_verified'] && ! $limit['identity_verified'])
                <div class="flash" style="background:var(--pri2);color:var(--pri);margin-top:12px">Faites vérifier votre identité (pièce + photo avec la pièce en main) pour passer à {{ number_format($limit['identity_limit'], 0, ',', ' ') }} $ par mois.</div>
            @endif
            @if($limit['next'])<div class="flash" style="background:var(--pri2);color:var(--pri);margin-top:12px">Encore {{ $limit['next']['orders_needed'] }} échange(s) réussi(s) et votre plafond passe à {{ number_format($limit['next']['limit'], 0, ',', ' ') }} $.</div>@endif
        </div>
        @endif
    </div>

    <div class="card" style="align-self:start">
        <div class="row sp"><div class="b">Vérification d'identité</div>
            @if($stage === 'approved')<span class="pill ok">✓ Vérifiée</span>
            @elseif($stage === 'pending')<span class="pill wait">En cours de vérification</span>
            @elseif($stage === 'document')<span class="pill info">Étape 2 sur 2</span>
            @elseif($stage === 'rejected')<span class="pill bad">Refusée</span>
            @else<span class="pill info">Non vérifiée</span>@endif</div>

        @if($user->role !== 'client')
            <p class="sm mut" style="margin-top:8px">Compte équipe : pas de vérification nécessaire.</p>
        @elseif($stage === 'approved')
            <p class="sm mut" style="margin-top:8px">Votre identité est vérifiée : votre plafond mensuel est plus élevé.</p>
        @elseif($stage === 'pending')
            <p class="sm mut" style="margin-top:8px">Votre dossier a été envoyé le {{ $submission->updated_at->format('d/m/Y H:i') }}. Nous vous prévenons dès qu'il est vérifié.</p>
        @elseif(! $user->email_verified_at)
            <p class="sm mut" style="margin-top:8px">Vérifiez d'abord votre adresse email.</p>
        @elseif($stage === 'document')
            <p class="sm mut" style="margin-top:8px"><b>Étape 2 sur 2.</b> Votre pièce est reçue. Prenez maintenant <b>une photo de vous qui tenez cette pièce</b> dans la main droite, avec une feuille portant ce code :</p>
            <div class="copy" style="margin:12px 0;text-align:center">
                <div class="num" style="font-size:38px;font-weight:800;letter-spacing:.2em;color:var(--pri)">{{ $challenge?->code }}</div>
                <div class="xs mut">À écrire à la main sur une feuille · valable jusqu'à {{ $challenge?->expires_at->format('H:i') }}</div>
                <form method="post" action="/profil/verification/code">@csrf<button class="btn ghost small" style="margin-top:8px">Nouveau code</button></form>
            </div>
            <form method="post" action="/profil/verification/selfie" enctype="multipart/form-data" class="kyc-form">@csrf
                <x-camera name="selfie" label="Selfie : votre visage, votre pièce dans la main droite et la feuille avec le code" facing="user" />
                @error('selfie')<div class="err">{{ $message }}</div>@enderror
                <button class="btn" style="margin-top:14px" disabled data-needs="selfie">Envoyer mon dossier</button>
            </form>
        @else
            @if($stage === 'rejected')<div class="flash bad" style="margin-top:10px">Dossier refusé : {{ $submission->rejection_reason }}</div>@endif
            <p class="sm mut" style="margin-top:8px"><b>Étape 1 sur 2.</b> Envoyez votre pièce d'identité : <b>carte d'électeur ou passeport</b>. Les photos se prennent avec la caméra, pas de fichier à envoyer.</p>
            <form method="post" action="/profil/verification/piece" enctype="multipart/form-data" class="kyc-form" id="docForm">@csrf
                <label>Type de pièce</label>
                <select name="id_type" id="idType">@foreach($idTypes as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                <x-camera name="id_front" label="Photo de la pièce (face avant, ou page photo du passeport), bien lisible" facing="environment" />
                <div id="backBox"><x-camera name="id_back" label="Face arrière de la carte d'électeur" facing="environment" /></div>
                @error('id_front')<div class="err">{{ $message }}</div>@enderror
                <button class="btn" style="margin-top:14px" disabled data-needs="id_front">Envoyer ma pièce</button>
            </form>
        @endif
        <p class="xs mut" style="margin-top:10px">Vos photos sont stockées de façon privée et ne servent qu'à vérifier votre identité. Les anciennes photos ou celles déjà utilisées par un autre compte sont refusées.</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
/* Caméra du navigateur : la photo est prise à l'instant, aucun fichier n'est choisi. */
document.querySelectorAll('.cam').forEach(function (box) {
    var v = box.querySelector('video'), cv = box.querySelector('canvas'), img = box.querySelector('img.shot'), input = box.querySelector('input[type=file]');
    var start = box.querySelector('.start'), snap = box.querySelector('.snap'), retake = box.querySelector('.retake'), msg = box.querySelector('.msg'), stream = null;
    function stop() { if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; } }
    function open() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { msg.textContent = "La caméra n'est pas disponible sur ce navigateur : utilisez l'application mobile Viratech."; return; }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: box.dataset.facing, width: { ideal: 1280 } }, audio: false }).then(function (s) {
            stream = s; v.srcObject = s; v.hidden = false; img.hidden = true; start.hidden = true; snap.hidden = false; retake.hidden = true; msg.textContent = '';
        }).catch(function () { msg.textContent = "Accès à la caméra refusé. Autorisez la caméra dans le navigateur, ou utilisez l'application mobile Viratech."; });
    }
    start.addEventListener('click', open); retake.addEventListener('click', open);
    snap.addEventListener('click', function () {
        cv.width = v.videoWidth; cv.height = v.videoHeight; cv.getContext('2d').drawImage(v, 0, 0);
        cv.toBlob(function (blob) {
            var file = new File([blob], box.dataset.name + '.jpg', { type: 'image/jpeg' }), dt = new DataTransfer();
            dt.items.add(file); input.files = dt.files;
            img.src = URL.createObjectURL(blob); img.hidden = false; v.hidden = true; snap.hidden = true; retake.hidden = false; stop();
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }, 'image/jpeg', 0.9);
    });
});
document.querySelectorAll('.kyc-form').forEach(function (f) {
    var btn = f.querySelector('button[data-needs]');
    function refresh() { var need = f.querySelector('input[name=' + btn.dataset.needs + ']'); btn.disabled = !(need && need.files && need.files.length); }
    f.addEventListener('change', refresh);
});
var t = document.getElementById('idType'), back = document.getElementById('backBox');
if (t && back) { var sync = function () { back.style.display = t.value === 'carte_electeur' ? '' : 'none'; if (t.value !== 'carte_electeur') { var i = back.querySelector('input[type=file]'); i.value = ''; } }; t.addEventListener('change', sync); sync(); }
window.addEventListener('pagehide', function () { document.querySelectorAll('video').forEach(function (v) { if (v.srcObject) v.srcObject.getTracks().forEach(function (t) { t.stop(); }); }); });
</script>
@endpush