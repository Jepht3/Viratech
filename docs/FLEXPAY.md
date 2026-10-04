# FlexPay : intégration et mise en service

FlexPay est l'agrégateur de paiement dans lequel Viratech est enregistré. Il sert à deux choses :

1. **Encaisser** : le client paie par **mobile money** (M-Pesa, Airtel Money, Orange Money, Afrimoney) ou par **carte Visa**. FlexPay confirme, Viratech reçoit les fonds sur son compte FlexPay. Aucune capture à envoyer : la commande avance toute seule.
2. **Verser** (opération inverse) : l'opérateur clique « Verser via FlexPay » et l'argent part du compte FlexPay vers le mobile money du client.

## Où se règle FlexPay

Tableau de bord web `/admin/parametres` ou application **Viratech Admin → Intégrations** (administrateur uniquement) :

| Réglage | Rôle |
|---|---|
| Activé | Active la vraie passerelle (sinon simulation locale) |
| Environnement | Test (sandbox) ou Production |
| Code marchand | Fourni par FlexPay |
| Jeton (token) | Fourni par FlexPay, **chiffré en base**, jamais réaffiché |
| Versement vers les clients | Active le bouton « Verser via FlexPay » |
| Adresse de rappel | À donner à FlexPay : `https://VOTRE-DOMAINE/api/webhooks/flexpay?secret=…` (le secret est généré tout seul) |

## Ce qui est sûr et ce qui reste à valider

La documentation officielle de FlexPay n'est pas publique. L'intégration s'appuie sur la bibliothèque open source tierce `devscast/flexpay-php`, qui liste les opérations « mobile », « carte », « vérification d'état » et **« payout » (versement)** :

| Opération | Adresse (production) | Champs principaux |
|---|---|---|
| Paiement mobile money | `POST https://backend.flexpay.cd/api/rest/v1/paymentService` | `merchant`, `type=1`, `phone` (243XXXXXXXXX), `reference`, `amount`, `currency`, `callbackUrl` |
| Paiement carte | `POST https://cardpayment.flexpay.cd/v1.1/pay` | `authorization` (Bearer), `merchant`, `reference`, `amount`, `currency`, `description`, `callback_url`, `approve_url`, `cancel_url`, `decline_url`, `home_url` |
| Vérification d'état | `GET https://backend.flexpay.cd/api/rest/v1/check/{orderNumber}` | réponse `transaction.status` : `0` = payé, `1` = échec |
| **Versement** | `POST https://backend.flexpay.cd/api/rest/v1/merchantPayOutService` | `merchant`, `type=1`, `reference`, `phone`, `amount`, `currency`, `callbackUrl` |

En test : mêmes adresses avec `beta-backend.flexpay.cd` et `beta-cardpayment.flexpay.cd`.

**À faire avant la mise en production, avec FlexPay :**
- obtenir la documentation officielle et comparer les champs ci-dessus ;
- faire activer le **versement (payout)** sur le compte marchand et connaître ses conditions (solde minimum, plafonds, frais, délais) ;
- connaître les frais FlexPay par opération et les inclure dans le barème ;
- essayer d'abord en **Test** avec un petit montant (encaissement, puis versement).

## Sécurité

- Le rappel (callback) est protégé par un secret dans l'adresse, mais son contenu n'est **jamais cru** : le serveur interroge FlexPay (`check/{orderNumber}`) pour connaître l'état réel avant de valider une étape.
- Une commande payée par FlexPay passe quand même par la **vérification** et le **contrôle de sécurité** de l'équipe.
- Le jeton FlexPay n'apparaît jamais dans les écrans, les journaux ou les réponses de l'API.

## Hors ligne (développement)

Sans FlexPay activé, une passerelle simulée est utilisée : un bouton « Simuler la confirmation FlexPay » (visible seulement avec `VIRATECH_SIMULATE_PAYPAL=true`) remplace la vraie confirmation.
