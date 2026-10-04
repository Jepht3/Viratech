<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Connexion · Viratech</title><link rel="icon" href="/favicon.png"><link rel="stylesheet" href="/css/viratech.css"></head>
<body><div class="auth">
    <div class="logo" style="justify-content:center"><img src="/img/logo.png" alt="Viratech" height="46"></div>
    <div class="card">
        <h1 style="font-size:24px">Connexion</h1>
        <p class="mut sm" style="margin-top:4px">Reçois en dollars, retire en local, sans stress.</p>
        <form method="post" action="/connexion">@csrf
            <label>Email</label><input type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')<div class="err">{{ $message }}</div>@enderror
            <label>Mot de passe</label><input type="password" name="password" required>
            <label class="row" style="font-weight:400;margin-top:12px"><input type="checkbox" name="remember" style="width:auto"> Rester connecté</label>
            <button class="btn block" style="margin-top:16px">Se connecter</button>
        </form>
    </div>
    <p class="sm mut" style="text-align:center;margin-top:16px">Pas encore de compte ? <a href="/inscription" class="b" style="color:var(--pri)">Créer un compte</a></p>
</div></body></html>
