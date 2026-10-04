<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Services\OrderWorkflow;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AdminOrderController extends Controller
{
    public function __construct(private OrderWorkflow $workflow) {}

    /** File de validation : commandes actives, les plus anciennes d'abord. */
    public function queue()
    {
        $active = Order::with(['corridor', 'steps', 'user'])->where('status', 'active')->oldest()->get();
        $done = Order::where('status', 'completed')->whereNotNull('completed_at')->latest('completed_at')->limit(50)->get();

        // Volume réel par mois (12 mois) et par échange (mois en cours).
        $labels = [];
        $series = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i);
            $labels[] = mb_substr($m->translatedFormat('M'), 0, 3);
            $series[] = (float) Order::whereIn('status', ['active', 'completed'])->whereYear('created_at', $m->year)->whereMonth('created_at', $m->month)->sum('amount');
        }
        $byCorridor = Order::with('corridor')->whereIn('status', ['active', 'completed'])->where('created_at', '>=', now()->startOfMonth())
            ->get()->groupBy('corridor_id')->map(fn ($g) => ['label' => $g->first()->corridor->label, 'amount' => (float) $g->sum('amount')])->sortByDesc('amount')->values();

        // Mouvements par canal d'après le ledger (théorique : à rapprocher des soldes réels), et montants dus aux clients.
        $channels = [];
        foreach (['paypal' => 'PayPal', 'equity' => 'Equity', 'mpesa' => 'M-Pesa', 'airtel' => 'Airtel Money', 'orange' => 'Orange Money', 'afrimoney' => 'Afrimoney'] as $k => $label) {
            $d = (float) LedgerEntry::where('account', 'company:'.$k)->sum('debit') - (float) LedgerEntry::where('account', 'company:'.$k)->sum('credit');
            if ($d != 0.0) {
                $channels[] = ['kind' => $k, 'label' => $label, 'amount' => $d];
            }
        }
        $owed = (float) LedgerEntry::where('account', 'like', 'client:%:payable')->sum('credit') - (float) LedgerEntry::where('account', 'like', 'client:%:payable')->sum('debit');

        return view('admin.queue', [
            'active' => $active,
            'volumeToday' => Order::whereDate('created_at', today())->whereIn('status', ['active', 'completed'])->sum('amount'),
            'feesToday' => Order::where('status', 'completed')->whereDate('completed_at', today())->sum('total_fee'),
            'avgMinutes' => $done->isEmpty() ? null : round($done->avg(fn ($o) => $o->created_at->diffInMinutes($o->completed_at))),
            'labels' => $labels, 'series' => $series, 'byCorridor' => $byCorridor, 'channels' => $channels, 'owed' => $owed,
        ]);
    }
    public function index(Request $request)
    {
        $q = Order::with(['corridor', 'user'])->latest();
        if ($s = $request->query('statut')) {
            $q->where('status', $s);
        }

        return view('admin.orders', ['orders' => $q->paginate(30)->withQueryString(), 'statut' => $s]);
    }

    public function show(string $reference)
    {
        $order = Order::with(['corridor', 'steps', 'proofs', 'user', 'operator'])->where('reference', $reference)->firstOrFail();

        return view('admin.order', ['order' => $order, 'simulate' => config('viratech.simulate_paypal')]);
    }

    public function step(Request $request, string $reference)
    {
        $order = Order::where('reference', $reference)->firstOrFail();
        $data = $request->validate(['key' => 'required|string', 'reference_code' => 'nullable|string|max:120', 'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120']);

        try {
            $this->workflow->complete($order, $data['key'], 'operator', $request->user(), $data['reference_code'] ?? null, $request->file('file')?->store('proofs'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reference_code' => $e->getMessage()]);
        }

        return back()->with('ok', 'Étape validée. Le client est prévenu.');
    }

    public function block(Request $request, string $reference)
    {
        $data = $request->validate(['reason' => 'required|string|max:200']);
        $this->guard(fn () => $this->workflow->block(Order::where('reference', $reference)->firstOrFail(), $request->user(), $data['reason']));

        return back()->with('ok', 'Étape bloquée, le client voit la raison.');
    }

    public function unblock(Request $request, string $reference)
    {
        $this->workflow->unblock(Order::where('reference', $reference)->firstOrFail(), $request->user());

        return back()->with('ok', 'Blocage levé.');
    }

    public function reject(Request $request, string $reference)
    {
        $data = $request->validate(['reason' => 'required|string|max:200']);
        $this->guard(fn () => $this->workflow->reject(Order::where('reference', $reference)->firstOrFail(), $request->user(), $data['reason']));

        return back()->with('ok', 'Commande refusée.');
    }

    private function guard(callable $fn): void
    {
        try {
            $fn();
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }
    }
}
