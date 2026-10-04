<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanyAccount;
use App\Models\Corridor;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderWorkflow;
use App\Support\Present;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** API de l'application Viratech Admin (personnel uniquement ; frais et comptes : administrateur uniquement). */
class AdminApiController extends Controller
{
    public function __construct(private OrderWorkflow $workflow) {}

    public function queue()
    {
        $active = Order::with(['corridor', 'steps', 'user'])->where('status', 'active')->oldest()->get();
        $done = Order::where('status', 'completed')->whereNotNull('completed_at')->latest('completed_at')->limit(50)->get();
        $labels = [];
        $series = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i);
            $labels[] = mb_substr($m->translatedFormat('M'), 0, 3);
            $series[] = (float) Order::whereIn('status', ['active', 'completed'])->whereYear('created_at', $m->year)->whereMonth('created_at', $m->month)->sum('amount');
        }
        $channels = [];
        foreach (['paypal' => 'PayPal', 'equity' => 'Equity', 'mpesa' => 'M-Pesa', 'airtel' => 'Airtel Money', 'orange' => 'Orange Money', 'afrimoney' => 'Afrimoney'] as $k => $label) {
            $d = (float) LedgerEntry::where('account', 'company:'.$k)->sum('debit') - (float) LedgerEntry::where('account', 'company:'.$k)->sum('credit');
            if ($d != 0.0) {
                $channels[] = ['kind' => $k, 'label' => $label, 'amount' => $d];
            }
        }

        return [
            'volume_today' => (float) Order::whereDate('created_at', today())->whereIn('status', ['active', 'completed'])->sum('amount'),
            'fees_today' => (float) Order::where('status', 'completed')->whereDate('completed_at', today())->sum('total_fee'),
            'avg_minutes' => $done->isEmpty() ? null : (int) round($done->avg(fn ($o) => $o->created_at->diffInMinutes($o->completed_at))),
            'owed_to_clients' => (float) LedgerEntry::where('account', 'like', 'client:%:payable')->sum('credit') - (float) LedgerEntry::where('account', 'like', 'client:%:payable')->sum('debit'),
            'channels' => $channels,
            'chart' => ['labels' => $labels, 'series' => $series],
            'active' => $active->map(fn ($o) => Present::order($o, false, true))->values(),
        ];
    }

    public function orders(Request $request)
    {
        $q = Order::with(['corridor', 'steps', 'user'])->latest();
        if ($s = $request->query('status')) {
            $q->where('status', $s);
        }

        return $q->limit(100)->get()->map(fn ($o) => Present::order($o, false, true))->values();
    }

    public function showOrder(string $reference)
    {
        return Present::order(Order::where('reference', $reference)->firstOrFail(), true, true);
    }

    public function step(Request $request, string $reference)
    {
        $order = Order::where('reference', $reference)->firstOrFail();
        $data = $request->validate(['key' => 'required|string', 'reference_code' => 'nullable|string|max:120', 'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120']);
        try {
            $this->workflow->complete($order, $data['key'], 'operator', $request->user(), $data['reference_code'] ?? null, $request->file('file')?->store('proofs'));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return Present::order($order->fresh(), true, true);
    }

    public function block(Request $request, string $reference)
    {
        $data = $request->validate(['reason' => 'required|string|max:200']);

        return $this->act(fn ($o) => $this->workflow->block($o, $request->user(), $data['reason']), $reference);
    }

    public function unblock(Request $request, string $reference)
    {
        return $this->act(fn ($o) => $this->workflow->unblock($o, $request->user()), $reference);
    }

    public function reject(Request $request, string $reference)
    {
        $data = $request->validate(['reason' => 'required|string|max:200']);

        return $this->act(fn ($o) => $this->workflow->reject($o, $request->user(), $data['reason']), $reference);
    }

    public function clients()
    {
        return User::where('role', 'client')->withCount('orders')->latest()->limit(100)->get()
            ->map(fn ($u) => Present::user($u) + ['orders_count' => $u->orders_count])->values();
    }

    public function setKyc(Request $request, User $user)
    {
        $data = $request->validate(['kyc_level' => 'required|integer|between:0,3']);
        $old = ['kyc_level' => $user->kyc_level];
        $user->update($data);
        AuditLog::record($request->user(), 'kyc.updated', $user, $old, $data);

        return Present::user($user->fresh());
    }

    // ── Réservé à l'administrateur ──

    public function fees()
    {
        return Corridor::with('tiers')->orderBy('sort')->get()->map(fn ($c) => Present::corridor($c))->values();
    }

    public function updateFees(Request $request, Corridor $corridor)
    {
        $data = $request->validate([
            'min_amount' => 'required|numeric|min:0', 'fixed_fee' => 'required|numeric|min:0',
            'eta_min_minutes' => 'required|integer|min:1', 'eta_max_minutes' => 'required|integer|gte:eta_min_minutes',
            'is_active' => 'nullable|boolean', 'hold_minutes' => 'nullable|integer|min:0|max:259200', 'tiers' => 'required|array|min:1',
            'tiers.*.min_amount' => 'required|numeric|min:0', 'tiers.*.percent' => 'required|numeric|min:0|max:100',
        ]);
        $old = ['min' => $corridor->min_amount, 'fixed' => $corridor->fixed_fee, 'tiers' => $corridor->tiers->map->only('min_amount', 'percent')->all()];
        $corridor->update([
            'min_amount' => $data['min_amount'], 'fixed_fee' => $data['fixed_fee'], 'eta_min_minutes' => $data['eta_min_minutes'],
            'eta_max_minutes' => $data['eta_max_minutes'], 'hold_minutes' => $data['hold_minutes'] ?? $corridor->hold_minutes, 'is_active' => $corridor->coming_soon ? false : (bool) ($data['is_active'] ?? $corridor->is_active),
        ]);
        $corridor->tiers()->delete();
        foreach ($data['tiers'] as $t) {
            $corridor->tiers()->create($t);
        }
        AuditLog::record($request->user(), 'fees.updated', $corridor, $old, $data);

        return Present::corridor($corridor->fresh('tiers'));
    }

    public function accounts()
    {
        return CompanyAccount::orderBy('id')->get()->map(fn ($a) => $a->only('id', 'kind', 'label', 'account_value', 'holder_name', 'is_active'))->values();
    }

    public function updateAccount(Request $request, CompanyAccount $account)
    {
        $data = $request->validate(['label' => 'required|string|max:80', 'account_value' => 'required|string|max:190', 'holder_name' => 'nullable|string|max:120', 'is_active' => 'nullable|boolean']);
        $old = $account->only('account_value', 'holder_name', 'label');
        $account->update($data + ['is_active' => (bool) ($data['is_active'] ?? $account->is_active)]);
        AuditLog::record($request->user(), 'company_account.updated', $account, $old, $data);

        return $account->fresh()->only('id', 'kind', 'label', 'account_value', 'holder_name', 'is_active');
    }

    public function createAccount(Request $request)
    {
        $data = $request->validate(['kind' => ['required', \Illuminate\Validation\Rule::in(array_keys(CompanyAccount::KINDS))], 'label' => 'nullable|string|max:80', 'account_value' => 'required|string|max:190', 'holder_name' => 'nullable|string|max:120']);
        $account = CompanyAccount::create(['kind' => $data['kind'], 'label' => $data['label'] ?? null ?: CompanyAccount::KINDS[$data['kind']], 'account_value' => $data['account_value'], 'holder_name' => $data['holder_name'] ?? null, 'is_active' => true]);
        AuditLog::record($request->user(), 'company_account.created', $account, null, $data);

        return response()->json($account->only('id', 'kind', 'label', 'account_value', 'holder_name', 'is_active'), 201);
    }

    public function deleteAccount(Request $request, CompanyAccount $account)
    {
        AuditLog::record($request->user(), 'company_account.deleted', $account, $account->only('kind', 'account_value'), null);
        $account->delete();

        return ['ok' => true];
    }

    private function act(callable $fn, string $reference)
    {
        $order = Order::where('reference', $reference)->firstOrFail();
        try {
            $fn($order);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return Present::order($order->fresh(), true, true);
    }
}
