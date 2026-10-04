<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Créer un compte · Viratech</title><link rel="icon" href="/favicon.png"><link rel="stylesheet" href="/css/viratech.css"></head>
<body><div class="auth">
    <div class="logo" style="justify-content:center"><img src="/img/logo.png" alt="Viratech" height="46"></div>
    <div class="card">
        <h1 style="font-size:24px">Créer un compte</h1>
        <form method="post" action="/inscription">@csrf
            <label>Nom complet (comme sur votre pièce d'identité)</label><input name="name" value="{{ old('name') }}" required>
            @error('name')<div class="err">{{ $message }}</div>@enderror
            <label>Email</label><input type="email" name="email" value="{{ old('email') }}" required>
            @error('email')<div class="err">{{ $message }}</div>@enderror
            <label>Téléphone (WhatsApp)</label><input name="phone" value="{{ old('phone') }}" placeholder="+243 ..." required>
            @error('phone')<div class="err">{{ $message }}</div>@enderror
            <label>Mot de passe (8 caractères minimum)</label><input type="password" name="password" required>
            @error('password')<div class="err">{{ $message }}</div>@enderror
            <label>Confirmer le mot de passe</label><input type="password" name="password_confirmation" required>
            <button class="btn block" style="margin-top:16px">Créer mon compte</button>
        </form>
    </div>
    <p class="sm mut" style="text-align:center;margin-top:16px">Déjà inscrit ? <a href="/connexion" class="b" style="color:var(--pri)">Se connecter</a></p>
</div></body></html>
