<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use App\Services\OrderWorkflow;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    /**
     * Rappel FlexPay. Protégé par un secret dans l'adresse ; le contenu du rappel n'est JAMAIS cru : on interroge FlexPay
     * pour connaître l'état réel du paiement ou du versement avant de valider l'étape.
     */
    public function flexpay(Request $request, OrderWorkflow $workflow)
    {
        $secret = (string) Setting::get('flexpay.callback_secret');
        abort_unless($secret !== '' && hash_equals($secret, (string) $request->query('secret')), 403);

        $ref = (string) ($request->input('reference') ?? '');
        $isPayout = str_ends_with($ref, '-OUT');
        $orderRef = $isPayout ? substr($ref, 0, -4) : $ref;
        $number = (string) ($request->input('orderNumber') ?? $request->input('order_number') ?? '');

        $order = Order::where('reference', $orderRef)->first()
            ?? ($number !== '' ? Order::where('flexpay_reference', $number)->orWhere('flexpay_payout_reference', $number)->first() : null);

        if ($order) {
            try {
                $isPayout || ($number !== '' && $number === $order->flexpay_payout_reference)
                    ? $workflow->confirmFlexpayPayout($order)
                    : $workflow->confirmFlexpay($order);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['ok' => true]);
    }
}