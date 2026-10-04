<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Corridor;
use App\Models\Order;
use App\Models\PayoutMethod;
use App\Services\FeeCalculator;
use App\Services\OrderWorkflow;
use App\Support\Present;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/** API de l'application cliente (mêmes règles métier que le site web). */
class ClientApiController extends Controller
{
    public function __construct(private OrderWorkflow $workflow, private FeeCalculator $fees) {}

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $orders = $user->orders()->with(['corridor', 'steps'])->latest()->get();
        $month = now()->startOfMonth();
        $labels = [];
        $series = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i);
            $labels[] = mb_substr($m->translatedFormat('M'), 0, 3);
            $series[] = (float) $orders->where('status', 'completed')->filter(fn ($o) => $o->completed_at && $o->completed_at->isSameMonth($m))->sum('net_amount');
        }

        return [
            'to_receive' => (float) $orders->where('status', 'active')->sum('net_amount'),
            'received_month' => (float) $orders->where('status', 'completed')->where('completed_at', '>=', $month)->sum('net_amount'),
            'used_month' => (float) $orders->whereIn('status', ['active', 'completed'])->where('created_at', '>=', $month)->sum('amount'),
            'monthly_limit' => $user->monthlyLimit(),
            'limit' => $user->limitInfo(),
            'active_count' => $orders->where('status', 'active')->count(),
            'total_exchanged' => (float) $orders->where('status', 'completed')->sum('amount'),
            'completed_count' => $orders->where('status', 'completed')->count(),
            'recent' => $orders->take(5)->map(fn ($o) => Present::order($o))->values(),
            'chart' => ['labels' => $labels, 'series' => $series],
            'unread_notifications' => $user->unreadNotifications()->count(),
        ];
    }

    public function corridors()
    {
        return Corridor::with('tiers')->orderBy('sort')->get()->map(fn ($c) => Present::corridor($c))->values();
    }

    public function quote(Request $request)
    {
        $data = $request->validate(['corridor' => 'required|exists:corridors,code', 'amount' => 'required|numeric|min:0']);
        $corridor = Corridor::with('tiers')->where('code', $data['corridor'])->firstOrFail();
        try {
            return ['ok' => true, 'quote' => $this->fees->quote($corridor, $data['amount']), 'eta' => $corridor->etaLabel()];
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function methods(Request $request)
    {
        return $request->user()->payoutMethods()->latest()->get()->map(fn ($m) => Present::method($m))->values();
    }

    public function storeMethod(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(PayoutMethod::KINDS))],
            'account_value' => 'required|string|max:120', 'holder_name' => 'required|string|max:120', 'label' => 'nullable|string|max:60',
        ]);
        if ($data['kind'] === 'paypal') {
            $request->validate(['account_value' => 'email']);
        }

        return response()->json(Present::method($request->user()->payoutMethods()->create($data)), 201);
    }

    public function destroyMethod(Request $request, PayoutMethod $method)
    {
        abort_unless($method->user_id === $request->user()->id, 404);
        $method->delete();

        return ['ok' => true];
    }

    public function orders(Request $request)
    {
        return $request->user()->orders()->with(['corridor', 'steps'])->latest()->limit(100)->get()->map(fn ($o) => Present::order($o))->values();
    }

    public function showOrder(Request $request, string $reference)
    {
        return Present::order($this->own($request, $reference), true);
    }

    public function storeOrder(Request $request)
    {
        $data = $request->validate([
            'corridor' => 'required|exists:corridors,code', 'amount' => 'required|numeric|min:0', 'payout_method_id' => 'required|integer',
            'source_kind' => 'nullable|in:mpesa,airtel,orange,afrimoney,equity', 'payment_method' => ['nullable', Rule::in(OrderWorkflow::PAYMENT_METHODS)],
        ]);
        $user = $request->user();
        $corridor = Corridor::with('tiers')->where('code', $data['corridor'])->firstOrFail();
        $method = $user->payoutMethods()->find($data['payout_method_id']);
        if (! $user->phone_verified_at) {
            return response()->json(['message' => 'Vérifiez votre numéro de téléphone avant de faire un échange.', 'code' => 'phone_not_verified'], 422);
        }
        if (! $method) {
            return response()->json(['message' => 'Choisissez un moyen de réception.'], 422);
        }

        if (($limit = $user->monthlyLimit()) !== null) {
            $used = $user->orders()->whereIn('status', ['active', 'completed'])->where('created_at', '>=', now()->startOfMonth())->sum('amount');
            if ($used + (float) $data['amount'] > $limit) {
                return response()->json(['message' => 'Ce montant dépasse votre plafond mensuel de '.number_format($limit, 0, ',', ' ').' $. Faites vérifier votre identité pour l\'augmenter.'], 422);
            }
        }

        try {
            $order = $this->workflow->create($user, $corridor, $data['amount'], $method, $data['source_kind'] ?? null, $data['payment_method'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(Present::order($order->fresh(), true), 201);
    }

    /** Le client envoie la capture de son paiement (obligatoire pour un virement direct ou un paiement PayPal manuel). */
    public function proof(Request $request, string $reference)
    {
        $order = $this->own($request, $reference);
        $data = $request->validate([
            'reference_code' => 'nullable|string|max:120',
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:8192',
        ], ['file.required' => 'Joignez la capture de votre paiement : elle est obligatoire.']);

        try {
            $this->workflow->complete($order, 'client_payment', 'client', $request->user(), $data['reference_code'] ?? null, $request->file('file')->store('proofs'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return Present::order($order->fresh(), true);
    }

    /** Paiement FlexPay : mobile money (demande sur le téléphone) ou carte Visa (renvoie l'adresse de la page de paiement). */
    public function flexpay(Request $request, string $reference)
    {
        $order = $this->own($request, $reference);
        $data = $request->validate(['phone' => 'nullable|string|max:30']);
        try {
            $order = $this->workflow->startFlexpay($order, $data['phone'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return Present::order($order, true);
    }

    public function simulatePayment(Request $request, string $reference)
    {
        abort_unless(config('viratech.simulate_paypal'), 404);
        $order = $this->own($request, $reference);
        try {
            if (str_starts_with((string) $order->payment_method, 'flexpay')) {
                $order = $order->flexpay_reference ? $order : $this->workflow->startFlexpay($order, $request->input('phone') ?: $request->user()->phone);
                $this->workflow->confirmFlexpay($order, trustSimulation: true);
            } else {
                $this->workflow->complete($order, 'client_payment', 'system', null, 'SIMULATION');
            }
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return Present::order($order->fresh(), true);
    }
    public function proofFile(Request $request, string $reference, int $proof)
    {
        $order = Order::where('reference', $reference)->firstOrFail();
        abort_unless($request->user()->isStaff() || $order->user_id === $request->user()->id, 404);
        $p = $order->proofs()->findOrFail($proof);
        abort_unless($p->path && Storage::exists($p->path), 404);

        return Storage::response($p->path);
    }

    public function notifications(Request $request)
    {
        return $request->user()->notifications()->limit(60)->get()->map(fn ($n) => [
            'id' => $n->id, 'title' => $n->data['title'] ?? '', 'body' => $n->data['body'] ?? '', 'reference' => $n->data['reference'] ?? null,
            'read' => (bool) $n->read_at, 'created_at' => $n->created_at->toIso8601String(),
        ])->values();
    }

    public function readNotification(Request $request, string $id)
    {
        $request->user()->notifications()->findOrFail($id)->markAsRead();

        return ['ok' => true];
    }

    public function readAllNotifications(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return ['ok' => true];
    }

    private function own(Request $request, string $reference): Order
    {
        return Order::where('reference', $reference)->where('user_id', $request->user()->id)->firstOrFail();
    }
}
