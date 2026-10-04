# Viratech

Plateforme d'échange manuel PayPal / Equity / mobile money pour freelances en RDC (USD uniquement).
Cahier des charges : [docs/CAHIER_DES_CHARGES.md](docs/CAHIER_DES_CHARGES.md) · Maquettes : `docs/design/`.

## Structure

| Dossier | Contenu |
|---|---|
| `viratech/` | Serveur Laravel : API, site web client, console opérateur/admin |
| `docs/` | Cahier des charges et maquettes |
| `mobile/` | *(à venir)* applications Flutter : Viratech (client) et Viratech Admin |

## Démarrer en local (Windows + Laragon)

Prérequis : PHP 8.3, Composer, MySQL, Mailpit (tous fournis par Laragon).

```powershell
cd viratech
composer install
copy .env.example .env      # puis régler DB_* (base viratech) et MAIL_PORT=1025
php artisan key:generate
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder   # facultatif : commandes de démonstration
php artisan serve                        # http://127.0.0.1:8000
php artisan test
```

Comptes de test locaux (mot de passe `password`) : `client@viratech.test`, `operateur@viratech.test`, `admin@viratech.test`.
**À supprimer avant toute mise en ligne.**

Emails de test : Mailpit, interface sur http://127.0.0.1:8025.

## Règles à retenir

- Tous les frais sont déduits du montant envoyé (pourcentage par palier + frais fixe), calcul en centimes entiers.
- Les étapes d'une commande sont **réelles** : elles ne passent à « faite » que sur un événement réel (action client, opérateur ou PayPal).
- Les versements restent validés **à la main** par un opérateur ; seul le dépôt PayPal sera automatisé (phase PayPal).
- Barème, minimums, délais et comptes de réception se modifient dans l'admin (`/admin/frais`, `/admin/comptes`), jamais dans le code.
