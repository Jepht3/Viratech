@extends('layouts.app')
@section('title', 'Notifications')
@section('heading')<h1>Notifications</h1>@endsection
@section('actions')
    @if(auth()->user()->unreadNotifications()->count())<form method="post" action="/notifications/lire">@csrf<button class="btn ghost small">Tout marquer comme lu</button></form>@endif
@endsection

@section('content')
<div class="grid g7">
    <div class="card">
        @forelse($notifications as $n)
            <a class="notif" href="/notifications/{{ $n->id }}">
                @if(! $n->read_at)<span class="unread"></span>@else<span style="width:8px"></span>@endif
                <div style="flex:1"><b>{{ $n->data['title'] ?? 'Notification' }}</b><div class="sm mut">{{ $n->data['body'] ?? '' }}</div></div>
                <span class="xs mut">{{ $n->created_at->diffForHumans() }}</span>
            </a>
        @empty
            <div class="empty">Aucune notification pour le moment.</div>
        @endforelse
        <div style="margin-top:12px">{{ $notifications->links() }}</div>
    </div>
    <div class="card" style="align-self:start">
        <div class="b">Préférences</div>
        <form method="post" action="/preferences-notifications">@csrf
            <label class="row" style="font-weight:500"><input type="checkbox" name="notify_email" value="1" style="width:auto" @checked(auth()->user()->notify_email)> Recevoir les emails</label>
            <label class="row" style="font-weight:500"><input type="checkbox" name="notify_push" value="1" style="width:auto" @checked(auth()->user()->notify_push)> Notifications dans la barre Android (application)</label>
            <div class="xs mut" style="margin-top:8px">Les alertes de sécurité sont toujours envoyées.</div>
            <button class="btn small" style="margin-top:12px">Enregistrer</button>
        </form>
    </div>
</div>
@endsection
