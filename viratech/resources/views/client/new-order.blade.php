@extends('layouts.app')
@section('title', 'Nouvel échange')
@section('heading')<h1>Nouvel échange</h1><div class="mut sm">Vous voyez exactement combien vous recevrez avant de payer.</div>@endsection

@section('content')
@php
    $active = $corridors->where('is_active', true);
    $first = $selected && $active->firstWhere('code', $selected) ? $selected : $active->first()?->code;
    $kindsFor = fn ($c) => \App\Models\PayoutMethod::kindsForTarget($c->target_kind);
@endphp
<form method="post" action="/echange" id="f" class="grid g7">@csrf
    <div class="card">
        <label style="margin-top:0">Type d'échange</label>
        <div class="grid" style="gap:10px">
            @foreach($corridors as $c)
                <label class="row card" style="padding:14px;border-radius:18px;cursor:{{ $c->coming_soon ? 'not-allowed' : 'pointer' }};margin:0;font-weight:500;{{ $c->coming_soon ? 'opacity:.55' : '' }}">
                    <input type="radio" name="corridor" value="{{ $c->code }}" style="width:auto" data-withdraw="{{ $c->isWithdrawal() ? 1 : 0 }}" data-payments='{{ json_encode($c->coming_soon ? [] : \App\Services\OrderWorkflow::allowedPayment($c)) }}' data-target="{{ $c->target_kind }}" data-source="{{ $c->source_kind }}" data-min="{{ $c->min_amount + 0 }}" {{ $c->coming_soon ? 'disabled' : '' }} {{ $first === $c->code ? 'checked' : '' }}>
                    <x-chan :kind="$c->source_kind" /><span class="mut">→</span><x-chan :kind="$c->target_kind" />
                    <span style="flex:1"><b>{{ $c->label }}</b><br><span class="xs mut">{{ $c->coming_soon ? 'Bientôt disponible' : 'Minimum '.($c->min_amount + 0).' $ · délai '.$c->etaLabel() }}</span></span>
                </label>
            @endforeach
        </div>

        <label>Montant envoyé (USD)</label>
        <input type="number" name="amount" id="amount" step="0.01" min="0" value="{{ old('amount') }}" placeholder="200.00" required>
        @error('amount')<div class="err">{{ $message }}</div>@enderror

        <div id="sourceBox" style="display:none">
            <label>Vous envoyez l'argent depuis</label>
            <select name="source_kind" id="source">
                <option value="mpesa">M-Pesa</option><option value="airtel">Airtel Money</option><option value="orange">Orange Money</option><option value="afrimoney">Afrimoney</option><option value="equity">Equity</option>
            </select>
        </div>
        <div id="modeBox">
            <label>Comment voulez-vous payer ?</label>
            <select name="payment_method" id="payment"></select>
            <div class="xs mut" id="payHelp" style="margin-top:6px"></div>
        </div>
        <label>Où voulez-vous recevoir l'argent ?</label>
        <select name="payout_method_id" id="payout" required>
            @foreach($methods as $m)
                <option value="{{ $m->id }}" data-kind="{{ $m->kind }}">{{ $m->kindLabel() }} · {{ $m->masked() }} · {{ $m->holder_name }}</option>
            @endforeach
        </select>
        @error('payout_method_id')<div class="err">{{ $message }}</div>@enderror
        <div class="xs mut" style="margin-top:6px" id="noMethod" hidden>Aucun moyen de réception pour ce type d'échange. <a href="/moyens-de-reception" class="b" style="color:var(--pri)">Ajouter un moyen</a></div>
    </div>

    <div class="card" style="align-self:start">
        <div class="b">Ce que vous recevrez</div>
        <div class="net" style="margin:14px 0"><div class="xs mut">Montant net</div><div class="v num" id="net">—</div></div>
        <div id="detail">
            <div class="kv"><span class="mut">Montant envoyé</span><b class="num" id="d_amount">—</b></div>
            <div class="kv"><span class="mut" id="d_pct_l">Frais</span><b class="num" id="d_pct">—</b></div>
            <div class="kv"><span class="mut">Frais fixes</span><b class="num" id="d_fix">—</b></div>
            <div class="kv"><span class="mut">Délai estimé</span><b id="d_eta">—</b></div>
        </div>
        <div class="xs mut" id="msg" style="margin-top:10px"></div>
        <div class="row" style="margin-top:12px;gap:8px;flex-wrap:wrap"><span class="pill ok xs">🔒 Frais garantis 20 min</span><span class="pill info xs">✓ Aucun frais caché</span></div>
        <button class="btn block" style="margin-top:16px" id="go">Continuer</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
const f = document.getElementById('f'), amount = document.getElementById('amount'), token = document.querySelector('input[name=_token]').value;
const kindsBy = { equity: ['equity'], mobile_money: ['mpesa','airtel','orange','afrimoney'], paypal: ['paypal'] };
const $ = id => document.getElementById(id), money = v => Number(v).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' $';
let timer;

function current() { return f.querySelector('input[name=corridor]:checked'); }

function refresh() {
    const c = current(); if (!c) return;
    const withdraw = c.dataset.withdraw === '1';
    fillPayment(JSON.parse(c.dataset.payments || '[]'));
    const src = $('source'); [...src.options].forEach(o => { const ok = c.dataset.source === 'equity' ? o.value === 'equity' : o.value !== 'equity'; o.hidden = !ok; o.disabled = !ok; });
    if (src.selectedOptions[0]?.disabled) src.value = [...src.options].find(o => !o.disabled).value;
    const allowed = kindsBy[c.dataset.target] || [], sel = $('payout'); let first = null;
    [...sel.options].forEach(o => { const ok = allowed.includes(o.dataset.kind); o.hidden = !ok; o.disabled = !ok; if (ok && !first) first = o; });
    if (sel.selectedOptions[0]?.disabled && first) sel.value = first.value;
    $('noMethod').hidden = !!first; $('go').disabled = !first;
    amount.min = c.dataset.min; amount.placeholder = c.dataset.min + '.00';
    quote();
}

const PAY = {
  paypal_invoice: ['Facture PayPal au montant exact (recommandé)', 'Vous payez la facture : le paiement est détecté automatiquement.'],
  paypal_account: ['Envoyer à notre compte PayPal', 'Vous envoyez à notre PayPal puis joignez la capture de votre paiement (obligatoire).'],
  transfer: ['Virement direct à notre compte', 'Vous envoyez à notre compte puis joignez la capture de votre paiement (obligatoire).'],
  flexpay_mobile: ['Mobile money via FlexPay', 'Paiement automatique : vous entrez votre numéro, puis vous validez avec votre code sur votre téléphone. Aucun numéro à copier, aucune capture.'],
  flexpay_card: ['Carte Visa via FlexPay', 'Paiement automatique sur la page sécurisée FlexPay. Aucune capture à envoyer.']
};
function fillPayment(keys) {
  const sel = $('payment'), keep = sel.value;
  sel.innerHTML = keys.map(k => '<option value="' + k + '">' + PAY[k][0] + '</option>').join('');
  if (keys.includes(keep)) sel.value = keep;
  syncPayment();
}
function syncPayment() {
  const v = $('payment').value;
  $('payHelp').innerHTML = v ? (v.startsWith('flexpay') ? '<span class="dot fx sm" style="display:inline-block;vertical-align:middle;margin-right:6px"></span>' : '') + PAY[v][1] : '';
  // Le réseau d'envoi (M-Pesa, Airtel…) n'est demandé que pour un virement direct : avec FlexPay, le numéro n'est pas affiché.
  $('sourceBox').style.display = v === 'transfer' ? '' : 'none';
}
function quote() {
    clearTimeout(timer);
    timer = setTimeout(async () => {
        const c = current(); if (!c || !amount.value) { $('net').textContent = '—'; $('msg').textContent = ''; return; }
        const r = await fetch('/echange/devis', {method: 'POST', headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json'}, body: JSON.stringify({corridor: c.value, amount: amount.value})}).then(r => r.json());
        if (!r.ok) { $('net').textContent = '—'; $('msg').textContent = r.message; return; }
        const q = r.quote; $('msg').textContent = '';
        $('net').textContent = money(q.net); $('d_amount').textContent = money(q.amount);
        $('d_pct_l').textContent = 'Frais (' + Number(q.percent) + ' %)'; $('d_pct').textContent = '− ' + money(q.percent_fee);
        $('d_fix').textContent = '− ' + money(q.fixed_fee); $('d_eta').textContent = r.eta;
    }, 250);
}

f.addEventListener('change', e => e.target.name === 'corridor' ? refresh() : null);
amount.addEventListener('input', quote);
$('payment').addEventListener('change', syncPayment);
refresh();
</script>
@endpush
