@extends('layouts.app')
@section('title', 'Paramètres')
@section('own-errors', '1')
@section('heading')<h1>Paramètres</h1><div class="mut sm">FlexPay, emails et notifications. Les clés sont chiffrées et ne sont jamais réaffichées.</div>@endsection

@section('content')
@if($errors->any())<div class="flash bad">{{ $errors->first() }}</div>@endif
<form method="post" action="/admin/parametres">@csrf
<div class="grid g2">
    <div class="card">
        <div class="row sp"><div class="b">FlexPay (mobile money et carte Visa)</div><label class="row" style="margin:0;font-weight:500"><input type="checkbox" name="flexpay_enabled" value="1" style="width:auto" @checked($s['flexpay.enabled'])> Activé</label></div>
        <label>Environnement</label><select name="flexpay_environment"><option value="sandbox" @selected($s['flexpay.environment'] === 'sandbox')>Test (sandbox)</option><option value="live" @selected($s['flexpay.environment'] === 'live')>Production</option></select>
        <label>Code marchand</label><input name="flexpay_merchant" value="{{ $s['flexpay.merchant'] }}">
        <label>Jeton (token) FlexPay</label><input type="password" name="flexpay_token" placeholder="{{ $s['flexpay.token']['set'] ? $s['flexpay.token']['masked'].' · laisser vide pour garder' : 'Collez le jeton' }}" autocomplete="off">
        <label class="row" style="font-weight:500"><input type="checkbox" name="flexpay_payout_enabled" value="1" style="width:auto" @checked($s['flexpay.payout_enabled'])> Activer le versement vers les clients (opération inverse)</label>
        <div class="xs mut">Le versement doit aussi être activé par FlexPay sur votre compte marchand, avec un solde suffisant.</div>
        <label>Adresse de rappel (à donner à FlexPay)</label><div class="copy mono sm">{{ $s['flexpay.callback_url'] }}</div>
        <div class="flash" style="background:var(--waitbg);color:var(--waitfg);margin-top:12px">Les adresses et champs de l'intégration viennent d'une bibliothèque tierce : faites un premier essai en mode Test avec un petit montant.</div>
    </div>
    <div class="grid" style="align-content:start">
        <div class="card">
            <div class="b">Emails (Resend)</div>
            <label>Clé API Resend</label><input type="password" name="mail_resend_key" placeholder="{{ $s['mail.resend_key']['set'] ? $s['mail.resend_key']['masked'].' · laisser vide pour garder' : 're_...' }}" autocomplete="off">
            <label>Adresse d'envoi (domaine vérifié sur Resend)</label><input type="email" name="mail_from_address" value="{{ $s['mail.from_address'] }}" placeholder="no-reply@viratech.cd">
            <label>Nom affiché</label><input name="mail_from_name" value="{{ $s['mail.from_name'] ?? 'Viratech' }}">
            <button class="btn ghost small" style="margin-top:12px" formaction="/admin/parametres/test-email">Envoyer un email de test à moi</button>
        </div>
        <div class="card">
            <div class="b">Notifications push (Google / Firebase)</div>
            <label>Identifiant du projet Firebase</label><input name="push_fcm_project_id" value="{{ $s['push.fcm_project_id'] }}" placeholder="mon-projet-12345">
            <label>Fichier du compte de service (JSON)</label><textarea name="push_fcm_service_account" rows="4" placeholder="{{ $s['push.fcm_service_account']['set'] ? 'Fichier enregistré · laisser vide pour garder' : 'Collez ici le contenu du fichier JSON téléchargé depuis Firebase' }}"></textarea>
            <div class="xs mut">Firebase → Paramètres du projet → Comptes de service → Générer une nouvelle clé privée.</div>
        </div>
    </div>
</div>
<button class="btn" style="margin-top:18px">Enregistrer les paramètres</button>
</form>
@endsection