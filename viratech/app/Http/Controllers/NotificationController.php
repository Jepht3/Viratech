<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications', ['notifications' => $request->user()->notifications()->paginate(30)]);
    }

    public function open(Request $request, string $id)
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        return redirect($n->data['url'] ?? '/notifications');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    public function preferences(Request $request)
    {
        $request->user()->update(['notify_email' => $request->boolean('notify_email'), 'notify_push' => $request->boolean('notify_push')]);

        return back()->with('ok', 'Préférences enregistrées.');
    }
}
