<?php

namespace App\Http\Controllers;

use App\Models\CompanyAccount;
use App\Models\Corridor;
use App\Models\Order;
use App\Models\OrderProof;
use App\Models\PayoutMethod;
use App\Services\FeeCalculator;
use App\Services\OrderWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function __construct(private OrderWorkflow $workflow, private FeeCalculator $fees) {}

    public function index()
    {
        return view('client.orders', ['orders' => auth()->user()->orders()->with('corridor')->latest()->paginate(20)]);
    }

    public function create(Request $request)
    {
        $corridors = Corridor::with('tiers')->orderBy('sort')->get();

        return view('client.new-order', [
            'corridors' => $corridors,
            'methods' => auth()->user()->payoutMethods()->get(),
            'selected' => $request->query('corridor'),
        ]);
    }

    /** Devis en direct : montant net exact avant de payer. */
    public function quote(Request $request)
    {
        $data = $request->validate(['corridor' => 'required|exists:corridors,code', 'amount' => 'required|numeric|min:0']);
        $corridor = Corridor::with('tiers')->where('code', $data['corridor'])->firstOrFail();

        try {
            return response()->json(['ok' => true, 'quote' => $this->fees->quote($corridor, $data['amount']), 'eta' => $corridor->etaLabel()]);
        } catch (InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'corridor' => 'required|exists:corridors,code',
            'amount' => 'required|numeric|min:0',
            'payout_method_id' => 'required|integer',
            'source_kind' => 'nullable|string|in:mpesa,airtel,orange,afrimoney,equity',
            'deposit_mode' => 'nullable|in:invoice,account',
        ]);
        $user = $request->user();
        $corridor = Corridor::with('tiers')->where('code', $data['corridor'])->firstOrFail();
        $method = $user->payoutMethods()->find($data['payout_method_id']);

        if (! $method) {
            return back()->withErrors(['payout_method_id' => 'Choisissez un moyen de réception.'])->withInput();
        }

        // Plafond mensuel selon le niveau de vérification.
        $limit = $user->monthlyLimit();
        if ($limit !== null) {
            $used = $user->orders()->whereIn('status', ['active', 'completed'])->where('created_at', '>=', now()->startOfMonth())->sum('amount');
            if ($used + (float) $data['amount'] > $limit) {
                return back()->withErrors(['amount' => 'Ce montant dépasse votre plafond mensuel de '.number_format($limit, 0, ',', ' ').' $. Faites vérifier votre identité pour l\'augmenter.'])->withInput();
            }
        }

        try {
            $order = $this->workflow->create($user, $corridor, $data['amount'], $method, $data['source_kind'] ?? null, $data['deposit_mode'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return redirect('/commandes/'.$order->reference)->with('ok', 'Commande créée. Suivez les étapes ci-dessous.');
    }

    public function show(Request $request, string $reference)
    {
        $order = $this->findFor($request, $reference);
        $order->load(['corridor', 'steps', 'proofs', 'user']);

        if ($request->user()->isStaff()) {
            return redirect('/admin/commandes/'.$order->reference);
        }

        return view('client.order', [
            'order' => $order,
            'paypalAccount' => CompanyAccount::forKind('paypal'),
            'depositAccount' => $order->corridor->isWithdrawal() ? null : CompanyAccount::forKind($order->corridor->source_kind),
            'simulate' => config('viratech.simulate_paypal'),
        ]);
    }

    /** Le client envoie sa preuve : signale un paiement PayPal ou valide l'étape « preuve de dépôt ». */
    public function proof(Request $request, string $reference)
    {
        $order = $this->findFor($request, $reference);
        $data = $request->validate(['reference_code' => 'nullable|string|max:120', 'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120']);
        $path = $request->file('file')?->store('proofs');
        $code = $data['reference_code'] ?? null;

        try {
            if ($order->corridor->isWithdrawal()) {
                $this->workflow->claimPayment($order, $request->user(), $code, $path);
            } else {
                $this->workflow->complete($order, 'deposit_proof', 'client', $request->user(), $code, $path);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reference_code' => $e->getMessage()]);
        }

        return back()->with('ok', 'Preuve envoyée. Un opérateur la vérifie.');
    }

    /** Hors ligne uniquement : simule le paiement de la facture PayPal (remplacé par le webhook PayPal en phase 2). */
    public function simulatePayment(Request $request, string $reference)
    {
        abort_unless(config('viratech.simulate_paypal'), 404);
        $order = $this->findFor($request, $reference);

        try {
            $this->workflow->complete($order, 'payment_received', 'system', null, 'SIMULATION');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['reference_code' => $e->getMessage()]);
        }

        return back()->with('ok', 'Paiement PayPal simulé (mode local).');
    }

    /** Sert une preuve uniquement à son propriétaire ou au personnel. */
    public function proofFile(Request $request, string $reference, OrderProof $proof)
    {
        $order = $this->findFor($request, $reference);
        abort_unless($proof->order_id === $order->id && $proof->path && Storage::exists($proof->path), 404);

        return Storage::response($proof->path);
    }

    private function findFor(Request $request, string $reference): Order
    {
        $order = Order::where('reference', $reference)->firstOrFail();
        abort_unless($request->user()->isStaff() || $order->user_id === $request->user()->id, 404);

        return $order;
    }
}
