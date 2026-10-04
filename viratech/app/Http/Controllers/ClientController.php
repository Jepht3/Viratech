<?php

namespace App\Http\Controllers;

use App\Models\Corridor;

class ClientController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();
        $orders = $user->orders()->with(['corridor', 'steps'])->latest()->get();

        $monthStart = now()->startOfMonth();
        $inProgress = $orders->where('status', 'active');

        // Montants réellement reçus, par mois, sur 12 mois.
        $labels = [];
        $series = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i);
            $labels[] = mb_substr($m->translatedFormat('M'), 0, 3);
            $series[] = (float) $orders->where('status', 'completed')
                ->filter(fn ($o) => $o->completed_at && $o->completed_at->isSameMonth($m))->sum('net_amount');
        }

        return view('client.dashboard', [
            'user' => $user,
            'toReceive' => $inProgress->sum('net_amount'),
            'receivedMonth' => $orders->where('status', 'completed')->where('completed_at', '>=', $monthStart)->sum('net_amount'),
            'usedMonth' => $orders->whereIn('status', ['active', 'completed'])->where('created_at', '>=', $monthStart)->sum('amount'),
            'activeCount' => $inProgress->count(),
            'recent' => $orders->take(4),
            'corridors' => Corridor::with('tiers')->orderBy('sort')->get(),
            'methods' => $user->payoutMethods()->get(),
            'labels' => $labels,
            'series' => $series,
        ]);
    }
}