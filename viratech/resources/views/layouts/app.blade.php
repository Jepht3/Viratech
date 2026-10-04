<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Viratech') · Viratech</title>
    <link rel="icon" href="/favicon.png">
    <link rel="stylesheet" href="/css/viratech.css">
</head>
@php
    $u = auth()->user();
    $unread = $u->unreadNotifications()->count();
    $markDays = $u->isStaff() ? [] : $u->orders()->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->pluck('created_at')->map->day->unique()->all();
@endphp
<body>
<header class="appbar">
    <button class="burger" type="button" aria-label="Ouvrir le menu" data-menu>☰</button>
    <div class="ttl"><img src="/img/logo-mark.png" alt="" height="30">Viratech</div>
    <a class="bell" href="/notifications" title="Notifications">🔔@if($unread)<span>{{ $unread }}</span>@endif</a>
</header>
<div class="scrim" data-menu-close></div>
<div class="shell">
    <aside class="side">
        <div class="profile">
            <a href="/profil" class="avatar" style="display:grid;overflow:hidden" title="Mon profil">@if($u->avatar_path)<img src="/avatar/{{ $u->id }}?v={{ $u->updated_at->timestamp }}" alt="" style="width:100%;height:100%;object-fit:cover">@else{{ $u->initials() }}@endif</a>
            <b>{{ $u->name }}</b>
            <span>{{ $u->email }}</span><br>
            <span class="role">{{ ['admin' => 'Administrateur', 'operator' => 'Opérateur', 'client' => 'Client · niveau '.$u->kyc_level][$u->role] }}</span>
        </div>
        <nav>
        @if($u->isStaff())
            <a class="nav {{ request()->is('admin') ? 'on' : '' }}" href="/admin">File de validation</a>
            <a class="nav {{ request()->is('admin/commandes*') ? 'on' : '' }}" href="/admin/commandes">Commandes</a>
            <a class="nav {{ request()->is('admin/clients*') ? 'on' : '' }}" href="/admin/clients">Clients</a>
            <a class="nav {{ request()->is('admin/verifications*') ? 'on' : '' }}" href="/admin/verifications">Vérifications d'identité @php($pk = \App\Models\KycSubmission::where('status', 'pending')->count())@if($pk)<span class="pill wait xs">{{ $pk }}</span>@endif</a>
            @if($u->isAdmin())
                <a class="nav {{ request()->is('admin/frais*') ? 'on' : '' }}" href="/admin/frais">Frais et minimums</a>
                <a class="nav {{ request()->is('admin/comptes*') ? 'on' : '' }}" href="/admin/comptes">Comptes de réception</a>
                <a class="nav {{ request()->is('admin/parametres*') ? 'on' : '' }}" href="/admin/parametres">Paramètres</a>
                <a class="nav {{ request()->is('admin/audit*') ? 'on' : '' }}" href="/admin/audit">Journal d'audit</a>
            @endif
        @else
            <a class="nav {{ request()->is('tableau-de-bord') ? 'on' : '' }}" href="/tableau-de-bord">Accueil</a>
            <a class="nav {{ request()->is('echange*') ? 'on' : '' }}" href="/echange/nouveau">Échanger</a>
            <a class="nav {{ request()->is('commandes*') ? 'on' : '' }}" href="/commandes">Historique</a>
            <a class="nav {{ request()->is('moyens-de-reception*') ? 'on' : '' }}" href="/moyens-de-reception">Moyens de réception</a>
            <a class="nav {{ request()->is('profil*') ? 'on' : '' }}" href="/profil">Mon profil @if($u->role === 'client' && (! $u->email_verified_at || (int) $u->kyc_level < 2))<span class="pill wait xs">!</span>@endif</a>
        @endif
            <a class="nav {{ request()->is('notifications*') ? 'on' : '' }}" href="/notifications">Notifications @if($unread)<span class="pill bad xs">{{ $unread }}</span>@endif</a>
        </nav>
        <div class="push"></div>
        @include('partials.calendar', ['markDays' => $markDays])
        <div class="foot"><form method="post" action="/deconnexion">@csrf<button class="btn ghost small block" style="color:#fff;border-color:rgba(255,255,255,.25)">Se déconnecter</button></form></div>
    </aside>
    <main class="main">
        <div class="top">
            <div>@yield('heading')</div>
            <div class="row">
                @yield('actions')
                <a class="bell" href="/notifications" title="Notifications">🔔@if($unread)<span>{{ $unread }}</span>@endif</a>
            </div>
        </div>
        @if(session('ok'))<div class="flash ok">{{ session('ok') }}</div>@endif
        @if($errors->any() && ! View::hasSection('own-errors'))<div class="flash bad">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
<nav class="tabbar" aria-label="Navigation principale">
    @if($u->isStaff())
        <a href="/admin" class="{{ request()->is('admin') ? 'on' : '' }}"><b>☰</b>File</a>
        <a href="/admin/commandes" class="{{ request()->is('admin/commandes*') ? 'on' : '' }}"><b>▤</b>Commandes</a>
        <a href="/admin/clients" class="{{ request()->is('admin/clients*') ? 'on' : '' }}"><b>☺</b>Clients</a>
    @else
        <a href="/tableau-de-bord" class="{{ request()->is('tableau-de-bord') ? 'on' : '' }}"><b>⌂</b>Accueil</a>
        <a href="/echange/nouveau" class="{{ request()->is('echange*') ? 'on' : '' }}"><b>⇄</b>Échanger</a>
        <a href="/commandes" class="{{ request()->is('commandes*') ? 'on' : '' }}"><b>▤</b>Historique</a>
    @endif
    <a href="/notifications" class="{{ request()->is('notifications*') ? 'on' : '' }}"><b>🔔</b>Alertes @if($unread)<em>{{ $unread }}</em>@endif</a>
</nav>
<script>
(function () {
    var b = document.body, open = function () { b.classList.add('menu-open'); }, close = function () { b.classList.remove('menu-open'); };
    document.querySelector('[data-menu]').addEventListener('click', open);
    document.querySelector('[data-menu-close]').addEventListener('click', close);
    document.querySelectorAll('.side a').forEach(function (a) { a.addEventListener('click', close); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    // Geste : glisser depuis le bord gauche pour ouvrir, vers la gauche pour fermer.
    var x0 = null, y0 = null;
    document.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; }, { passive: true });
    document.addEventListener('touchend', function (e) {
        if (x0 === null || window.innerWidth > 980) return;
        var dx = e.changedTouches[0].clientX - x0, dy = Math.abs(e.changedTouches[0].clientY - y0);
        if (dy < 60 && dx > 70 && x0 < 28) open();
        if (dy < 60 && dx < -70 && b.classList.contains('menu-open')) close();
        x0 = null;
    }, { passive: true });
})();
</script>@stack('scripts')
</body>
</html>
