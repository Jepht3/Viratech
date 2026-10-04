# Viratech : ce qui reste à faire (audit du 4 octobre 2026)

Légende : 🔴 bloquant pour la mise en service · 🟠 important · 🟡 confort

## 1. Paiements : ce qui est simulé aujourd'hui
| | État | À faire |
|---|---|---|
| 🔴 **PayPal Business (API)** | **Simulé.** La « facture PayPal payée » est un bouton de simulation. Rien n'est branché sur PayPal. | Créer l'app PayPal (sandbox puis production), implémenter la création de facture, les webhooks (paiement reçu, litige, remboursement) et le rapprochement. Vérifier que le compte Business du pays peut utiliser ces API. |
| 🔴 **FlexPay** | Code écrit d'après une bibliothèque tierce, **jamais testé** contre FlexPay. | Obtenir la documentation officielle et le code marchand, tester en sandbox, faire activer le versement (payout). Voir `docs/FLEXPAY.md`. |
| 🟠 **Frais FlexPay** | Non pris en compte dans le barème. | Connaître leurs frais par opération et les inclure. |
| 🟠 **Remboursements** | Une commande refusée après réception du paiement n'a pas de parcours de remboursement. | Étape « remboursement » avec preuve, suivie dans le ledger. |
| 🟠 **Litiges / rétrofacturations** | Le webhook de litige PayPal n'existe pas (pas d'API). Pas de module « litiges ». | Module litiges, blocage automatique d'une commande quand PayPal signale un litige. |
| 🟡 **Crypto** | « Bientôt disponible ». | Phase ultérieure. |
| 🔴 **Comptes de réception** | Equity et mobile money valent encore `000…` (provisoire). | Les saisir dans Admin → Comptes de réception. |

## 2. Notifications et emails
- 🔴 **Notifications push dans l'app Android** : le serveur sait les envoyer (Firebase), mais l'app ne les reçoit pas encore. Il faut créer le projet Firebase, ajouter `google-services.json` aux deux apps (`com.viratech.app` et `com.viratech.app.admin`), intégrer `firebase_messaging`, enregistrer le jeton (`POST /api/devices`) et demander l'autorisation d'afficher les notifications (Android 13+).
- 🔴 **Emails en production** : créer un compte Resend, vérifier le domaine d'envoi, saisir la clé dans Admin → Paramètres. Sans cela les codes email ne partent pas.
- 🟠 Les emails sont envoyés pendant la requête : prévoir une file d'attente (queue) en production.
- 🟡 Aucun e-mail « mot de passe oublié » : fonction absente (voir §4).

## 3. Vérification d'identité
- 🟠 Les photos d'identité sont stockées **sans chiffrement** sur le disque du serveur : chiffrer, limiter l'accès et définir une durée de conservation.
- 🟠 Sur le site, la caméra du navigateur n'a pas pu être testée ici : à essayer sur un vrai téléphone (HTTPS obligatoire hors localhost).
- 🟡 Aucune reconnaissance faciale automatique : la comparaison est manuelle (choix volontaire). Un service tiers pourrait aider plus tard.
- 🟡 Pas de page « supprimer mes données » (droit de suppression).

## 4. Comptes et sécurité
- 🟠 **Mot de passe oublié** : absent (web et apps).
- 🟠 **Double authentification (2FA)** pour l'équipe : prévue au cahier des charges, absente.
- 🟠 **Gestion de l'équipe** : pas d'écran pour créer, désactiver ou changer le rôle d'un opérateur (aujourd'hui par la base de données).
- 🟠 Les comptes de test (`admin@viratech.test`, mot de passe `password`) **doivent être supprimés** avant toute mise en ligne.
- 🟡 Pas de blocage de compte après trop d'échecs de connexion (seulement une limite de débit).

## 5. Mise en service
- 🔴 **Serveur** : aucun serveur installé. Reprendre les scripts de LeWebPOS (`deploy/`), nginx, HTTPS, base MySQL, sauvegardes quotidiennes, tâche planifiée `php artisan schedule:run` (expiration des commandes), file d'attente.
- 🔴 **GitHub Actions** : `tests.yml` et `mobile.yml` existent mais la publication vers le serveur et le déploiement du site ne sont pas écrits. Secrets à créer (clé de signature Android, accès serveur).
- 🔴 **Signature des APK** : créer la clé de signature Android (à garder hors du dépôt ET hors de cet ordinateur).
- 🟠 **Publication des versions** : la mise à jour intégrée lit `GET /api/application/version`, mais **aucun écran admin ne publie une nouvelle version** (une seule ligne de départ). À ajouter, ou à alimenter depuis GitHub Actions.
- 🟠 **Adresse du serveur dans les apps** : définie à la compilation (`API_URL`). Les APK de test pointent vers le PC.
- 🟡 Site `https://…` : nom de domaine, pages « Conditions d'utilisation » et « Confidentialité » absentes.

## 6. Juridique et conformité
- 🔴 Enregistrement auprès de la **Banque centrale du Congo** et société constituée (annoncés, à faire). Faire valider par un juriste local : agrément nécessaire, KYC/AML, déclarations, conservation des données.
- 🟠 Conditions d'utilisation : délais de sécurité PayPal, frais, remboursements, litiges.

## 7. Fonctions de gestion manquantes
- 🟠 **Rapprochement quotidien / clôture de journée** : le ledger existe, mais pas d'écran de rapprochement avec les soldes réels des comptes.
- 🟠 **Rapports** (volume, marge par échange, taux de litiges) : seulement les indicateurs de la file de validation.
- 🟠 **Recherche** d'une commande ou d'un client, filtres par date.
- 🟠 **Support client** : pas de page d'aide, pas de lien WhatsApp ni de chat lié à la commande.
- 🟡 Pas d'export (PDF/CSV) de l'historique ni de reçus.
- 🟡 Pas de parrainage ni de codes promo.
- 🟡 Pas d'écran de choix de langue (français uniquement).
- 🟡 Pas d'application iOS ; publication Google Play non faite.

## 8. Qualité technique
- ✅ 45 tests du serveur (flux de commande, preuves, délai de sécurité, FlexPay simulé, identité, plafonds, paramètres, API).
- 🟠 Les applications Flutter n'ont que 3 petits tests : aucun test des écrans.
- 🟠 Les écrans de l'app **Admin** ont été vus en partie seulement sur téléphone (file de validation) : détail de commande, vérifications d'identité, frais, comptes et intégrations restent à essayer à la main.
- 🟡 Écran de démarrage (splash) et icône : icône faite, écran de démarrage par défaut.
- 🟡 Les maquettes HTML de `docs/design/` datent de l'ancien thème.

## 9. Décisions à prendre
1. Plafond de 150 $ sans identité : **par mois** (retenu) ou **par échange** ?
2. Délai de sécurité PayPal : 14 / 7 / 3,5 jours (voir cahier des charges §14.2). À ajuster avec ton taux de litiges réel.
3. Valeurs définitives des comptes de réception et du barème.