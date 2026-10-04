# Cahier des charges — Viratech
**Plateforme d'échange manuel PayPal / Banque / Mobile Money / Crypto pour freelances en RDC**

Version 1.3 — 4 octobre 2026 — Statut : validé en grande partie, prêt pour la conception (développement local hors ligne d'abord)

> Les chiffres de marché et de frais ci-dessous sont des **hypothèses de travail** issues de la connaissance du secteur. Elles doivent être revérifiées (grilles tarifaires officielles M-Pesa, Airtel, Equity, PayPal, cours USDT) avant le lancement.

---

## 1. Vision et principe directeur

**Promesse :** « Reçois en dollars, retire en local, sans stress. »

Le freelance reçoit de l'argent sur PayPal (ou en crypto). Il crée un ordre dans l'app, envoie les fonds, et l'opérateur (toi) lui verse l'équivalent sur Equity, M-Pesa ou Airtel Money, moins une marge.

**Principe directeur : les décaissements sont toujours validés manuellement ; seule la réception PayPal est automatisée.**
- **Dépôt PayPal : via l'API PayPal Business** (facture ou compte affiché, voir 12.1). L'app détecte le paiement reçu automatiquement.
- **Décaissements (Equity, M-Pesa, Airtel, USDT) : aucune API, exécutés et validés à la main** par l'opérateur.
- L'app est un **registre d'ordres + un portefeuille interne (ledger) + un outil de suivi**. L'argent réel bouge hors de l'app, l'opérateur confirme dans l'app.
- Avantages : zéro intégration coûteuse, lancement rapide, contrôle total du risque, conformité plus simple au démarrage.

---

## 2. Analyse du marché congolais

### 2.1 Contexte
| Élément | Constat |
|---|---|
| Devise du service | **USD uniquement** (pas de CDF). Les freelances sont payés en USD et retirent en USD. Pas de risque de change. |
| Mobile money dominant | M-Pesa (Vodacom), Airtel Money, Orange Money, Afrimoney (Africell). Bien plus répandu que la banque. |
| Banque | Equity BCDC, Rawbank, TMB, Ecobank, Access. Comptes USD courants chez les freelances « bancarisés ». |
| PayPal | Peu ou pas de retrait direct vers la RDC. Le « retrait » passe donc par un intermédiaire, c'est la douleur n°1 des freelances. |
| Crypto | USDT (TRC-20 / BEP-20) très utilisé via Binance P2P, surtout par les jeunes et traders. Confiance croissante, volatilité faible sur stablecoin. |
| Concurrence informelle | Revendeurs WhatsApp / Facebook, taux opaques, délais variables, risque d'arnaque. C'est ton vrai concurrent. |

### 2.2 Corridors évalués

| Corridor | Demande | Marge possible | Risque | Verdict |
|---|---|---|---|---|
| **PayPal → Equity (USD)** | Très forte (freelances Upwork, Fiverr, designers, devs) | 10 % / 7 % / 6 % selon montant (min 150 $) | Rétrofacturation PayPal, gel de compte | **Cœur du produit — Phase 1** |
| **PayPal → M-Pesa / Airtel / autres (USD)** | Très forte (pas de compte bancaire requis) | Idem + 2 $ fixes (min 150 $) | Idem + limites mobile money | **Cœur du produit — Phase 1** |
| **Crypto (USDT)** | Forte et en croissance | à définir | Faible (irréversible) | **Coming soon** |
| **Mobile money → PayPal** | Moyenne (achats en ligne, abonnements, outils SaaS) | 10 % fixe (min 100 $) | Fraude à l'achat, litiges | **Phase 1** |
| **Equity → PayPal** | Moyenne | 10 % fixe (min 100 $) | Idem | **Phase 1** |
| **PayPal → Crypto** | Moyenne | 3 à 5 % | Rétrofacturation | Phase 2 |
| **M-Pesa ↔ Airtel** | Moyenne, mais interopérabilité native existe | 0,5 à 1,5 % | Faible | **Phase 2, produit d'appel/fidélisation, pas centre de profit** |

### 2.3 Recommandation : le plus adapté

1. **Le coeur de métier est PayPal → compte local.** C'est là que la douleur est la plus forte et la marge la plus élevée. Tu le fais déjà manuellement : l'app industrialise ce que tu sais faire.
2. **Crypto : coming soon.** Elle reste un levier futur (réception irréversible, donc sans risque de rétrofacturation) mais n'est pas activée au lancement.
3. **Retraits prioritaires :** Equity (USD) et M-Pesa. Airtel Money en troisième. Orange Money en option.
4. **M-Pesa ↔ Airtel et Equity → PayPal** servent à fidéliser et à remplir ta liquidité, pas à gagner de l'argent. À activer en Phase 2.
5. **Format :** **deux applications Android séparées (Viratech pour les clients, Viratech Admin pour l'équipe) plus un site web complet** : tout peut se faire sur le site sans installer l'app. La majorité des freelances congolais sont sur smartphone.
6. **Canal de confiance :** WhatsApp reste le canal de support. L'app y envoie des liens de suivi, mais ne remplace pas la relation humaine.

### 2.4 Risques majeurs (à traiter dès le départ)
| Risque | Mitigation |
|---|---|
| **Rétrofacturation / litige PayPal** (le client récupère l'argent après ton virement) | Règle : paiement PayPal en « Biens et services » uniquement, fonds **disponibles** avant versement, plafonds progressifs pour nouveaux clients, preuve de transaction obligatoire, réserve de sécurité sur gros montants. |
| **Gel du compte PayPal** par volume atypique | Plusieurs comptes professionnels, volume progressif, reporter l'activité sur crypto quand le client le permet. |
| **Conformité réglementaire** (BCC, KYC/AML, cellule de renseignement financier CENAREF) | Consulter un juriste local avant de passer à l'échelle, KYC dès un seuil, journal d'audit complet. |
| **Fraude / identité** | KYC par paliers, vérification des noms (nom PayPal = nom du compte), liste noire. |
| **Erreur humaine opérateur** | Double validation au-delà d'un seuil, rapprochement quotidien, ledger immuable. |
| **Risque de liquidité** (pas assez de fonds sur Equity / M-Pesa) | Tableau de bord des soldes réels vs. dus aux clients. |
| **Variation des frais** (barème modifié entre l'ordre et le paiement) | Barème de frais figé à la création de l'ordre pour une durée limitée. |
| **Réglementaire** | Activité déclarée auprès de la Banque centrale du Congo (BCC) et exercée via une société constituée. Voir 12.4. |

---

## 3. Utilisateurs et rôles

| Rôle | Description | Droits |
|---|---|---|
| **Client (freelance)** | Reçoit de l'argent via PayPal/crypto, veut retirer en local | Voir solde, créer un ordre, ajouter des moyens de retrait, suivre, historique |
| **Opérateur** | Toi / ton équipe | Valider réceptions, effectuer les paiements, marquer « payé », gérer litiges |
| **Admin** | Toi | Barème de frais, plafonds, délais, utilisateurs, KYC, rapports, double validation |

---

## 4. Modèle fonctionnel

### 4.1 Le portefeuille (solde)
Chaque client possède un **portefeuille interne en USD** (USDT en option) géré par un **ledger à double entrée** :
- **Solde disponible** : peut être retiré immédiatement.
- **Solde en attente** : fonds reçus mais en période de sécurité (ex. 24 à 72 h pour PayPal).
- **Solde bloqué** : ordre en cours de traitement.

Deux modes de fonctionnement (au choix du client) :
- **Mode direct (par défaut)** : un ordre = une conversion = un paiement. Aucun solde conservé.
- **Mode portefeuille** : le client dépose (PayPal / mobile money) → crédite son solde → retire quand il veut, en une ou plusieurs fois. Utile pour les freelances payés régulièrement.

### 4.2 Cycle de vie d'un ordre
```
CRÉÉ → EN ATTENTE DU PAIEMENT CLIENT → PREUVE ENVOYÉE → VÉRIFICATION (opérateur)
     → FONDS CONFIRMÉS → PAIEMENT EN COURS → PAYÉ (preuve opérateur) → TERMINÉ
     (branches : EXPIRÉ, REJETÉ, LITIGE, REMBOURSÉ)
```
Chaque changement d'état est horodaté, attribué à un utilisateur et non modifiable.

### 4.3 Flux type : PayPal → Equity
1. Le client choisit « PayPal → Equity », saisit 100 USD.
2. **Il choisit obligatoirement son moyen de réception (Equity, M-Pesa, Airtel ou autre mobile money) et le compte associé avant de valider l'ordre.** L'app affiche : frais, **montant net à recevoir**, délai estimé. Barème figé 20 min.
3. L'app affiche les **instructions de paiement** (email PayPal de réception, note à mettre, mode « Biens et services »).
4. Le client paie sur PayPal puis téléverse la capture + l'ID de transaction.
5. L'opérateur reçoit une notification, vérifie la réception réelle sur PayPal, clique « Fonds confirmés ».
6. L'opérateur fait le virement Equity, téléverse la preuve, clique « Payé ».
7. Le client est notifié (push + WhatsApp + email) et voit la preuve.

### 4.4 Moyens de retrait (carnet de bénéficiaires)
Le client enregistre ses destinations, vérifiées par l'opérateur à la première utilisation :
- Equity (numéro de compte, nom, devise)
- M-Pesa (numéro, nom)
- Airtel Money (numéro, nom)
- Orange Money (option)
- Crypto : *coming soon*
- Règle de sécurité : le nom du bénéficiaire doit correspondre au nom KYC, sauf validation exceptionnelle.

### 4.5 Frais et marges (USD uniquement) — barème décidé

Pas de taux de change : tout est en USD, le revenu vient uniquement des frais. Les pourcentages, minimums et frais fixes ci-dessous sont des **paramètres configurables par l'admin** dans le back-office (sans redéploiement, avec historique des changements).

**A. Retrait PayPal → Equity** (minimum **150 $**)
| Montant | Frais |
|---|---|
| 150 à 499 $ | 10 % |
| 500 à 2 000 $ | 7 % |
| 2 001 $ et plus | 6 % |

**B. Retrait PayPal → mobile money (M-Pesa, Airtel Money et autres)** (minimum **150 $**)
Même barème que A, **plus 2 $ de frais fixes**. Les 2 $ s'appliquent uniquement quand le mobile money est impliqué (dépôt ou retrait), jamais sur les échanges Equity.

**C. Mobile money (M-Pesa, Airtel, autres) → PayPal** (minimum **100 $**)
**10 % quel que soit le montant, plus 2 $ de frais fixes** (mobile money impliqué).

**C bis. Equity → PayPal** (minimum **100 $**) : **mêmes règles que C, 10 % quel que soit le montant.**

**D. Crypto** : *coming soon* (affiché dans l'app, non activable en phase 1).

Exemples (frais prélevés sur le montant envoyé) :
| Opération | Calcul | Net reçu |
|---|---|---|
| 200 $ PayPal → Equity | 200 − 10 % (20 $) | **180 $** |
| 200 $ PayPal → M-Pesa | 200 − 10 % (20 $) − 2 $ | **178 $** |
| 1 000 $ PayPal → Equity | 1 000 − 7 % (70 $) | **930 $** |
| 1 000 $ PayPal → Airtel | 1 000 − 7 % (70 $) − 2 $ | **928 $** |
| 2 500 $ PayPal → Equity | 2 500 − 6 % (150 $) | **2 350 $** |
| 100 $ M-Pesa → PayPal | 100 − 10 % (10 $) − 2 $ | **88 $** |

Règles : les paliers s'appliquent au montant total (pas par tranche) ; un ordre sous le minimum est refusé avec un message clair ; le client voit toujours le net exact avant de confirmer ; barème figé 20 min après création de l'ordre. *(Confirmé : tous les frais, pourcentage plus frais fixe, sont **déduits du montant envoyé**. Le client voit le net exact avant de payer.)*

### 4.6 Crypto — *coming soon*
Non disponible en phase 1. L'écran « Crypto » affiche un état « Bientôt disponible » avec inscription à la liste d'attente. Les éléments techniques (réseaux TRC-20 / BEP-20, hash, confirmations) sont conservés pour une phase ultérieure.
### 4.7 KYC par paliers
| Niveau | Exigence | Plafond indicatif (à définir) |
|---|---|---|
| 0 | Téléphone + email vérifiés | 500 USD / mois (minimum d'ordre : 150 USD) |
| 1 | Pièce d'identité + selfie | 500 USD / mois |
| 2 | Justificatif d'activité freelance (profil Upwork, contrat) | 3 000 USD / mois |
| 3 | Dossier renforcé | Sur mesure |

---

## 5. Exigences fonctionnelles

### 5.1 Application client (app mobile Viratech et site web)
| ID | Exigence | Priorité |
|---|---|---|
| C-01 | Inscription / connexion par téléphone (OTP) + email, 2FA optionnelle | Must |
| C-02 | Tableau de bord : soldes (disponible / en attente / bloqué), derniers ordres, barème de frais | Must |
| C-03 | Création d'ordre avec simulateur (montant → net reçu), inversion « je veux recevoir X » | Must |
| C-04 | Instructions de paiement dynamiques selon corridor + compte à compter à rebours | Must |
| C-05 | Téléversement de preuve (image/PDF) + ID de transaction / hash | Must |
| C-06 | Suivi d'ordre en timeline temps réel | Must |
| C-07 | Carnet de moyens de retrait (CRUD, vérification) | Must |
| C-08 | Historique, filtres, export PDF/CSV, reçus | Must |
| C-09 | Notifications push + WhatsApp (lien) + email | Must |
| C-10 | Parcours KYC (upload pièces) | Must |
| C-11 | Mode portefeuille : dépôt puis retrait différé | Should |
| C-12 | Rappel de statut et alerte de délai dépassé | Could |
| C-13 | Parrainage et code promo | Could |
| C-14 | Chat / support lié à l'ordre | Should |
| C-15 | Multilingue FR (par défaut), EN, puis Lingala / Swahili | Should |
| C-16 | Mode sombre / clair | Should |

### 5.2 Back-office opérateur / admin
| ID | Exigence | Priorité |
|---|---|---|
| A-01 | **File de validation** des ordres, triée par urgence, avec SLA visible | Must |
| A-02 | Fiche ordre : preuve, infos client, historique, boutons « Fonds confirmés / Rejeter / Payé » | Must |
| A-03 | Saisie des preuves de paiement sortant (capture, référence) | Must |
| A-04 | Gestion des **pourcentages de frais** par corridor et par palier, avec historique | Must |
| A-05 | Vue des **soldes réels** par canal (PayPal, Equity, M-Pesa, Airtel, USDT) vs. engagements clients | Must |
| A-06 | Validation KYC, bénéficiaires, adresses crypto | Must |
| A-07 | Gestion des litiges et remboursements | Must |
| A-08 | Double validation au-delà d'un seuil (4 yeux) | Should |
| A-09 | Rapports : volume, marge, profit par corridor, délai moyen, taux de litige | Must |
| A-10 | Journal d'audit exportable, non modifiable | Must |
| A-11 | Rapprochement quotidien (clôture de journée) | Should |
| A-12 | Liste noire et signaux de fraude (doublons de preuves, noms non concordants) | Should |
| A-13 | Gestion des rôles et permissions | Must |

### 5.3 Règles métier clés
- RG-01 : un ordre expire si aucune preuve n'est envoyée sous 30 min (configurable).
- RG-02 : on ne verse **jamais** avant « Fonds confirmés » par un opérateur.
- RG-03 : PayPal accepté uniquement en « Biens et services », fonds disponibles.
- RG-04 : un même ID de transaction ne peut pas être utilisé deux fois.
- RG-05 : le nom du compte PayPal / bénéficiaire doit correspondre au nom KYC.
- RG-06 : tout ordre au-dessus du seuil X nécessite une double validation.
- RG-07 : barème de frais figé pour une durée fixe (20 min), recalcul après expiration.
- RG-10 : le moyen de réception est obligatoire à la création de l'ordre ; toute modification demande une revalidation opérateur.
- RG-08 : toute action sensible est tracée (qui, quand, avant/après).
- RG-09 : arrondis et devises gérés en entiers (centimes), jamais en flottants.

---

## 6. Exigences non fonctionnelles

| Domaine | Exigence |
|---|---|
| **Performance** | Chargement < 3 s sur 3G, budget JS < 200 Ko initial, mode hors-ligne pour consulter l'historique |
| **Mobile-first** | Conçu à 360 px de large d'abord, gestes simples, grandes zones tactiles (≥ 48 px) |
| **Disponibilité** | 99,5 % visé, sauvegarde quotidienne chiffrée |
| **Sécurité** | HTTPS, chiffrement des données sensibles au repos, 2FA opérateurs obligatoire, journalisation, limitation de débit, protection anti-fraude de base, secrets hors code |
| **Données** | Intégrité comptable (ledger immuable, écritures équilibrées), conservation des pièces 5 ans |
| **Conformité** | KYC/AML, politique de confidentialité, CGU claires sur délais / rétrofacturation, avis juridique local |
| **Accessibilité** | Contraste AA, taille de texte ajustable, libellés clairs sans jargon |
| **Observabilité** | Logs, alertes sur ordres bloqués, métriques de SLA |

---

## 7. Architecture technique (alignée sur LeWebPOS)

Même socle et mêmes méthodes de livraison que LeWebPOS, pour réutiliser l'expérience et les outils déjà en place.

| Couche | Choix |
|---|---|
| Serveur / API | **Laravel (PHP)** avec API JSON, tests automatisés (comme LeWebPOS) |
| Base de données | **MySQL/PostgreSQL**, transactions ACID, ledger à double entrée |
| Site web client | Site responsive complet : tout se fait sans passer par l'app (inscription, KYC, échange, suivi, retrait, historique) |
| Site web admin | Console opérateur complète sur le web (sécurisée, 2FA) |
| **App mobile Client** | **Flutter, Android**, package `com.viratech.app` |
| **App mobile Admin** | **Flutter, Android**, application séparée, package `com.viratech.app.admin` |
| Notifications | **Email** + **notifications push dans la barre de notification Android** (Firebase, comme LeWebPOS) + **centre de notifications dans l'app et sur le site** (cloche, lu / non lu) |
| Stockage fichiers | Stockage privé, URLs signées (preuves, KYC) |
| Temps réel | Rafraîchissement / SSE pour la timeline d'ordre et la file opérateur |
| Hébergement | Serveur dédié ou VPS, nginx, scripts de déploiement et sauvegarde (reprendre `deploy/` de LeWebPOS) |

### 7.1 Système de mises à jour (identique à LeWebPOS)
- Point d'API `GET /application/version` renvoyant `build`, `version`, `url` (APK client), `url_admin` (APK admin, réservé aux administrateurs) et `notes` (liste de nouveautés en phrases courtes, fichier `nouveautes.txt` publié avec chaque version).
- Au démarrage, l'app compare son `build` à celui du serveur. Si plus récent : fenêtre « Une nouvelle version est disponible ! » avec les nouveautés, bouton **Mettre à jour maintenant** et **Plus tard** (proposée une seule fois par version). Bouton « Vérifier la mise à jour » dans « À propos ».
- Android installe le nouvel APK par-dessus (données et connexion conservées, même signature).
- **GitHub Actions** : tests automatiques, déploiement du site sur le serveur avec sauvegarde de la base et migrations, compilation et signature des deux APK uniquement quand le dossier `mobile/` change, publication des APK sur le serveur. Le numéro de build augmente seul.
- Dépôt GitHub **privé** au nom de l'application (Viratech).

### 7.2 Démarrage hors ligne sur ce PC
Phase locale : développement dans `D:\Repay exchange`, base locale, serveur de test local, aucune mise en ligne. Le dépôt Git local est initialisé dès le départ ; la connexion au dépôt GitHub Viratech et le déploiement serveur sont faits plus tard, quand l'application est prête.

### 7.3 Modèle de données (entités principales)
`users`, `kyc_documents`, `wallets`, `ledger_entries` (double entrée), `payout_methods`, `corridors`, `fee_rules` (pourcentages, paliers, frais fixes, minimums), `orders`, `order_steps` (étapes réelles horodatées), `proofs`, `disputes`, `channel_balances`, `company_accounts` (comptes de réception), `notifications`, `device_tokens`, `app_versions`, `audit_logs`.

**Principe du ledger :** chaque mouvement crée deux écritures (débit/crédit) entre comptes internes (client, entreprise, frais, réserve). Le solde est une somme, jamais une valeur écrasée.

---
## 8. Design et expérience (tendances Q4 2026)

### 8.1 Direction
**« Calme financier » : clair, chaleureux, rassurant.** L'argent est un sujet anxiogène : le design doit réduire le stress, pas le renforcer.

### 8.2 Principes
1. **Mobile-first, thumb-friendly** : actions principales dans la moitié basse de l'écran, barre de navigation inférieure.
2. **Bento grid** pour le dashboard : cartes modulaires de tailles variées (solde, frais, ordre en cours, retraits).
3. **Typographie expressive et lisible** : grands chiffres pour les montants, police variable (type Inter / Geist / Plus Jakarta Sans), tabular-nums pour les nombres.
4. **Clair par défaut + mode sombre soigné**, pas de noir pur, contrastes AA.
5. **Transparence radicale** : décomposition visible (montant, frais en %, net) à chaque étape.
6. **Statut avant tout** : timeline d'ordre claire, « Où est mon argent ? » répond en 1 seconde.
7. **Micro-interactions sobres** : transitions courtes, animations de confirmation, haptique, pas de décor gratuit.
8. **Trust signals** : badges KYC, preuve de paiement visible, délai moyen réel, avis clients.
9. **Faible bande passante** : pas de lourdes images, icônes SVG, squelettes de chargement.
10. **Ton humain** en français simple, vouvoiement ou tutoiement cohérent, zéro jargon bancaire.

### 8.3 Système visuel
| Élément | Choix |
|---|---|
| Fond | Blanc cassé chaud `#F6F5F1` |
| Encre | Vert nuit `#0F1B17` |
| Primaire | Émeraude `#0E7C5A` |
| Accent | Ambre `#F2B544` (attente, mise en avant) |
| Succès / Alerte / Erreur | `#16A34A` / `#F59E0B` / `#DC2626` |
| Couleurs de marque des canaux | M-Pesa vert, Airtel rouge, Equity bordeaux, PayPal bleu, USDT turquoise (utilisées uniquement en pastilles) |
| Rayons | 20 à 28 px sur cartes, 999 px sur boutons |
| Ombres | Très douces, bordures fines 1 px |
| Icônes | Trait 1,75 px, style cohérent |

### 8.4 Écrans à concevoir
**Client :** Accueil/Dashboard · Nouvel échange (simulateur) · Instructions de paiement · Envoi de preuve · Suivi d'ordre · Moyens de retrait · Historique · KYC · Profil/Sécurité.
**Opérateur :** File de validation · Fiche ordre · Soldes par canal · Frais & marges · Clients/KYC · Rapports · Journal d'audit.

Maquettes fournies : dossier `design/` (dashboard client, parcours mobile, console opérateur).

---

## 9. Plan de livraison

| Phase | Contenu | Durée indicative |
|---|---|---|
| **0 — Cadrage** | Validation du CDC, avis juridique, grilles de marges, choix du nom | 1 à 2 semaines |
| **1 — MVP** | Comptes + KYC niveau 0/1, corridors PayPal→Equity, PayPal→mobile money, mobile money→PayPal (crypto : coming soon), ordres, preuves, soldes, back-office de validation, notifications | 6 à 8 semaines |
| **2 — Consolidation** | Mode portefeuille, double validation, rapports, rapprochement, parrainage, Equity→PayPal, M-Pesa↔Airtel | 4 à 6 semaines |
| **3 — Échelle** | Automatisations partielles (notifications WhatsApp API), multi-opérateurs, app installable publiée | selon traction |

### Indicateurs de succès (3 premiers mois)
- Délai moyen entre « fonds confirmés » et « payé » < 30 min en heures ouvrables
- Taux de litige < 1 %
- Taux de clients récurrents > 40 %
- 0 perte sur rétrofacturation
- NPS > 50

---

## 10. Hors périmètre (Phase 1)
- Aucune API de décaissement (Equity, M-Pesa, Airtel) : seule l'API PayPal de réception est intégrée.
- Pas de carte bancaire, pas de compte de paiement stocké pour l'entreprise dans l'app.
- Pas de conversion crypto/crypto, pas de trading.
- Publication sur le Play Store : plus tard (les APK sont d'abord distribués par le site, avec mise à jour intégrée).

---

## 11. Questions ouvertes à trancher
1. Quel(s) compte(s) PayPal réceptionnent (perso, pro, plusieurs) et quels plafonds par compte ?
2. *(Réglé)* Frais fixe de 2 $ : uniquement quand un mobile money est impliqué (dépôt ou retrait).
3. Politique exacte de délai « fonds en attente » PayPal avant versement ?
4. Seuil de double validation et de KYC niveau 2 ?
5. Type exact d'agrément BCC et forme de société (voir section 12) ?
6. Orange Money / Afrimoney : à inclure dès le MVP ou plus tard ?
7. Nom définitif et identité de marque ?

---

## 12. Compléments (v1.1)

### 12.1 Dépôt PayPal via l'API Business (réception automatisée)
Compte PayPal **Business** de l'entreprise, deux modes de dépôt proposés au client :

| Mode | Fonctionnement | Rapprochement | Recommandation |
|---|---|---|---|
| **A. Facture PayPal (Invoicing API)** | L'app crée une facture au montant exact liée à l'ordre ; le client la paie via le lien. | Automatique : webhook de paiement reçu → l'ordre passe à « Fonds reçus ». | **Mode principal** (référence unique, montant exact, zéro erreur de saisie) |
| **B. Compte PayPal affiché** | L'app affiche l'adresse PayPal de réception et un code d'ordre à mettre en note ; le client envoie le montant. | Détection via webhooks / Transaction Search API, rapprochement par montant + note + e-mail expéditeur ; **validation manuelle de l'opérateur si ambiguïté**. | Alternative pour les clients qui refusent les liens |

Points de conception :
- Intégration : Invoicing API, Webhooks (paiement reçu, litige ouvert, remboursement), Transaction Search API pour le rapprochement. Un compte développeur et une app PayPal (sandbox puis production) sont nécessaires.
- Les événements **litige / remboursement** reçus par webhook bloquent automatiquement le versement et alertent l'opérateur.
- Le versement reste **manuel** même quand les fonds PayPal sont détectés automatiquement (principe : l'argent sort uniquement sur validation humaine).
- Contrôle de nom : le nom du payeur PayPal doit correspondre au nom KYC.
- À vérifier avec PayPal : pays de rattachement du compte Business, droits d'utilisation de ces API, politique d'usage pour ce type d'activité, délais de mise à disposition des fonds.
- Rétrofacturation : le risque subsiste (le webhook ne le supprime pas). Règles de plafonds et d'attente restent applicables.

### 12.2 Délais de traitement (USD)
Le délai affiché au client dépend du type d'opération. Fourchette globale : **5 minutes à 1 jour**. Les valeurs ci-dessous sont proposées et à confirmer par l'opérateur ; elles sont **configurables par corridor** dans le back-office.

| Opération | Délai affiché |
|---|---|
| PayPal (facture payée) → M-Pesa / Airtel | 5 à 30 min |
| PayPal → Equity | 30 min à 4 h (jusqu'à 1 jour hors heures ouvrées) |
| Mobile money → PayPal | 30 min à 1 jour |
| M-Pesa ↔ Airtel | 5 à 15 min |

Le SLA de chaque ordre est visible côté opérateur ; un ordre qui dépasse son délai remonte en tête de file et notifie le client.

### 12.3 Moyen de réception choisi à l'avance
Le client doit renseigner **avant de payer** son moyen de réception (Equity, M-Pesa, Airtel ou autre mobile money) : l'ordre ne peut pas être créé sans bénéficiaire valide, déjà présent dans son carnet ou ajouté dans le parcours.

### 12.4 Cadre juridique
- Activité à **enregistrer auprès de la Banque centrale du Congo** et exercée par une **société constituée** en RDC.
- À faire valider par un juriste local avant le lancement public : catégorie d'agrément BCC applicable (prestataire de services de paiement, change, monnaie électronique, etc.), capital minimum, obligations KYC/AML, déclarations, conservation des données.
- L'app doit supporter : journal d'audit exportable, rapports réglementaires, registre des opérations, politique de conformité.
- Les CGU mentionnent la société, son numéro d'enregistrement et le régulateur.

### 12.5 Comptes de réception de l'entreprise (configurables)
Affichés au client selon le corridor, modifiables par l'admin sans redéploiement. **Valeurs actuelles = valeurs provisoires** :

| Canal | Utilisation | Valeur actuelle |
|---|---|---|
| PayPal Business | Réception des dépôts PayPal | `jepht3@gmail.com` |
| Equity | Réception des dépôts depuis Equity | `0000000000000000000000` (provisoire) |
| Mobile money (M-Pesa, Airtel, autres) | Réception des dépôts mobile money | `0000000000000` (provisoire) |

Règle : tout changement de compte de réception est journalisé (qui, quand, ancienne et nouvelle valeur) et demande une double validation.

---

## 13. Compléments (v1.2)

### 13.1 Suivi d'ordre en étapes réelles (exigence centrale)
Le client ne doit jamais attendre dans le flou. La timeline reflète **uniquement des événements réels**, jamais une barre de progression simulée.

**Règles**
- Une étape ne passe à « faite » que lorsqu'un événement réel l'a déclenchée : webhook PayPal, action de l'opérateur, ou action du client.
- Chaque étape affiche son **heure réelle**, et qui l'a faite (Système, Opérateur, Vous).
- L'étape en cours affiche le **temps écoulé** et le délai habituel de cette étape.
- Si l'ordre est bloqué, l'étape affiche la **raison** (ex. « Preuve illisible, merci de renvoyer »).
- Preuve de versement visible à la fin (capture ou référence de transaction).
- Chaque étape déclenche une notification (email + push + centre de notifications).
- Le **montant net à recevoir** reste affiché en haut de l'ordre, du début à la fin.

**Étapes PayPal → Equity / mobile money**
1. Ordre créé *(Système)*
2. En attente de votre paiement PayPal (facture ou compte affiché) *(Vous)*
3. Paiement PayPal reçu *(Système, via webhook ; Opérateur en mode compte affiché)*
4. Contrôle de sécurité en cours *(Opérateur)*
5. Versement en cours vers votre compte *(Opérateur)*
6. Versement effectué, preuve disponible *(Opérateur)*
7. Terminé

**Étapes Mobile money / Equity → PayPal**
1. Ordre créé *(Système)*
2. En attente de votre dépôt sur le compte Viratech *(Vous)*
3. Preuve envoyée *(Vous)*
4. Dépôt vérifié *(Opérateur)*
5. Envoi PayPal en cours *(Opérateur)*
6. Paiement PayPal envoyé, identifiant de transaction disponible *(Opérateur)*
7. Terminé

### 13.2 Montant net toujours visible
Avant de payer et pendant tout le suivi, l'écran affiche la décomposition : montant envoyé, pourcentage, frais fixe, **montant net que vous recevrez**. Tous les frais sont déduits du montant envoyé.

### 13.3 Deux applications mobiles + site web
| Surface | Public | Contenu |
|---|---|---|
| App **Viratech** | Clients | Solde, nouvel échange, suivi réel, moyens de réception, historique, KYC, notifications |
| App **Viratech Admin** | Opérateurs et admin | File de validation, fiche ordre, confirmation d'étapes, preuves, soldes par canal, barème de frais, comptes de réception, KYC, litiges, notifications |
| **Site web client** | Clients | Toutes les fonctions de l'app client, sans obligation d'installer l'app |
| **Site web admin** | Opérateurs et admin | Toutes les fonctions de l'app admin |

Les quatre surfaces partagent la même API et la même base.

### 13.4 Notifications
- **Email** : confirmation d'ordre, chaque étape clé, ordre terminé avec reçu, alerte de blocage, sécurité (nouvelle connexion, changement de compte).
- **Push dans la barre de notification Android** (même application fermée), via Firebase.
- **Centre de notifications dans l'app et sur le site** : cloche avec compteur, liste lu / non lu, lien direct vers l'ordre.
- **Côté admin** : nouvel ordre, preuve reçue, ordre proche du délai (SLA), litige PayPal, liquidité basse.
- Préférences par utilisateur (email et push activables séparément) ; les alertes de sécurité ne sont pas désactivables.

### 13.5 Mises à jour et GitHub
Voir 7.1 (système identique à LeWebPOS) et 7.2 (développement hors ligne d'abord).

### 13.6 Plan de livraison ajusté
| Phase | Contenu |
|---|---|
| **0 — Local hors ligne** | Projet dans `D:\Repay exchange`, Git local, base locale, API + site web client et admin, ledger, barème de frais, timeline d'étapes réelles, notifications email et centre interne |
| **1 — Applications** | App Viratech (client) et Viratech Admin (Flutter), push Firebase, système de mise à jour |
| **2 — Intégration PayPal Business** | Facture, webhooks, rapprochement (en mode test PayPal sandbox) |
| **3 — Mise en ligne** | Dépôt GitHub privé Viratech, GitHub Actions, serveur, sauvegardes, avis juridique et enregistrement BCC |
| **4 — Après lancement** | Crypto (coming soon), parrainage, rapports avancés |

---

## 14. Compléments (v1.3, remplace 13.1 et 12.2 là où ils diffèrent)

### 14.1 Étapes réelles : preuve des deux côtés
Le client envoie **la capture de son propre paiement** ; l'équipe envoie **la capture du versement**. Même parcours pour tous les échanges :

1. **Commande créée** *(système)*
2. **Paiement du client** : il paie sur un de *nos* comptes (PayPal, mobile money, Equity) et **joint la capture de la transaction (obligatoire)**. Exceptions confirmées automatiquement, sans capture : *facture PayPal payée* et *paiement FlexPay* (mobile money ou carte Visa).
3. **Paiement vérifié par Viratech** *(opérateur)* : l'argent est bien arrivé, le nom du payeur correspond au client.
4. **Contrôle de sécurité** *(opérateur)* : bloqué pendant le **délai de sécurité** (voir 14.2).
5. **Versement lancé** *(opérateur)*
6. **Versement effectué** *(opérateur)* : **capture du versement obligatoire**, que le client retrouve dans sa commande. Alternative : « Verser via FlexPay » (mobile money), confirmé automatiquement.
7. **Terminé** *(système)*

Le client ne peut jamais valider les étapes de l'opérateur, et l'opérateur ne peut pas se substituer au client pour déclarer son paiement.

### 14.2 Rapidité selon l'origine du paiement et délai de sécurité PayPal
- **Paiements qui arrivent par mobile money, Equity ou carte Visa** : traités rapidement (5 à 30 min), sans délai de sécurité.
- **Paiements qui arrivent par PayPal** : volontairement plus lents, car il faut s'assurer qu'aucun **litige ou rétrofacturation** n'est ouvert avant de verser.

**Repères sur les délais PayPal** (recherche du 4 octobre 2026) :
- un acheteur peut ouvrir un **litige PayPal jusqu'à 180 jours** après le paiement ;
- une **rétrofacturation par la banque de la carte** se fait en général dans les **120 jours** (Visa, Amex, Discover), 90 à 120 jours pour Mastercard ;
- un nouveau compte vendeur PayPal peut voir ses fonds **retenus jusqu'à 21 jours** ;
- depuis janvier 2024, la protection vendeur PayPal ne couvre plus les rétrofacturations « article non reçu » faites auprès de la banque.

**Conclusion : aucun délai court n'élimine le risque** (le seul délai 100 % sûr serait de 180 jours, inutilisable). Le délai réduit le risque, il se combine avec les plafonds, la vérification d'identité et le contrôle du nom du payeur.

**Politique retenue (réglable par l'administrateur, page « Frais et minimums », champ « Délai de sécurité standard »)** :

| Client | Délai avant versement |
|---|---|
| Nouveau, ou identité non vérifiée, ou moins de 3 échanges réussis | **14 jours** (standard × 2) |
| Identité vérifiée, au moins 3 échanges réussis | **7 jours** (standard) |
| Identité vérifiée, au moins 10 échanges réussis | **3,5 jours** (standard ÷ 2) |

Le délai court à partir de la vérification du paiement. Seul un **administrateur** peut le lever, avec une raison obligatoire, journalisée. Cette politique est une recommandation prudente : à ajuster avec l'expérience réelle (taux de litiges observé) et un conseil juridique/financier.

Mesures complémentaires recommandées : paiements en « Biens et services » uniquement (jamais « Entre proches »), refuser les payeurs dont le nom ne correspond pas au client, plafonds bas pour les nouveaux clients, ne verser que sur des comptes au nom du client.

### 14.3 FlexPay (encaissement et versement)
Voir `docs/FLEXPAY.md`. Résumé : le client peut payer par mobile money ou carte Visa via FlexPay ; l'opérateur peut verser au mobile money du client via FlexPay (opération inverse), sous réserve de l'activation du service de versement par FlexPay. Réglages (marchand, jeton, environnement, versement) dans **Paramètres** (web) ou **Intégrations** (application Admin).

### 14.4 Paramètres administrables
Dans le tableau de bord et l'application Admin (administrateur uniquement) : FlexPay ; **emails via Resend** (clé API, adresse d'envoi) ; **notifications push Google / Firebase** (identifiant du projet, compte de service JSON). Les secrets sont chiffrés en base et jamais réaffichés. L'application mobile devra recevoir le fichier `google-services.json` du projet Firebase pour recevoir les notifications push.

### 14.5 Email, identité, photo et plafonds (remplace la vérification par SMS)
Pas de vérification par SMS : il n'existe pas de fournisseur SMS gratuit pour la RDC (Firebase : 10 SMS/jour gratuits puis payant ; Africa's Talking : environ 0,03 $ le SMS). Le numéro de téléphone devient un simple contact facultatif. Seuls deux contrôles comptent :

1. **Email vérifié (obligatoire)** : code à 6 chiffres envoyé par email (Resend en production, Mailpit en local). Sans email vérifié : aucun échange.
2. **Identité vérifiée** : sans elle, **les échanges sont limités à 150 $ par mois seulement**.

**Vérification d'identité en deux temps, avec la caméra uniquement (aucun fichier à envoyer)** :
- *Étape 1* : le client photographie sa **pièce d'identité : carte d'électeur (avant et arrière) ou passeport (page photo)**.
- *Étape 2* : on lui demande **une photo de lui tenant cette pièce dans la main droite**, avec une feuille portant un **code à 5 chiffres** généré par Viratech (valable 30 min) et écrit à la main.
- L'équipe compare le visage, la pièce et le code, puis approuve ou refuse (raison montrée au client). Rien n'est approuvé automatiquement.
- Dans l'application, les photos passent uniquement par l'appareil photo. Sur le site, elles passent par la caméra du navigateur (pas de sélecteur de fichier) ; le navigateur ne permet pas de l'imposer de façon absolue côté serveur, l'application mobile reste donc la voie la plus sûre.
- **Contre le contournement** : code éphémère (une vieille photo ne le contient pas), empreinte SHA-256 des images comparée à celles des autres comptes, même image utilisée plusieurs fois, photo ancienne ou floue signalée, 3 tentatives par jour, photos stockées de façon privée.

**Plafonds mensuels** : email non vérifié : 0 $ · email vérifié, identité non vérifiée : **150 $** · identité vérifiée : **3 000 $** · sur mesure : 10 000 $ par défaut. **Montée automatique** réservée aux identités vérifiées, avec les échanges terminés : ×1,5 dès 3, ×2 dès 10, ×3 dès 25. L'administrateur peut fixer un plafond à la main pour un client.
### 14.6 Identité visuelle
Couleurs du logo : vert (principal), anthracite, cyan (accent). Cartes de statistiques toutes de couleurs différentes (vert, cyan, orange, anthracite) pour ne jamais répéter la même couleur ; l'ambre reste réservé au statut « en cours ».

## 15. Numéros de réception par réseau (v1.4)

- Chaque réseau a son propre numéro de réception : PayPal, Equity, M-Pesa, Airtel Money, Orange Money, Afrimoney. L'administrateur peut les modifier, en ajouter et en supprimer (site et application Viratech Admin).
- **FlexPay activé** : le client ne voit aucun numéro. Il arrive directement sur l'écran de paiement automatique FlexPay (mobile money ou carte Visa), avec les instructions, sans capture à envoyer.
- **FlexPay désactivé** : le client choisit son réseau et paie vers le numéro de ce réseau (M-Pesa vers le numéro M-Pesa, Airtel vers le numéro Airtel), puis envoie la capture de son paiement.
- Si la source est PayPal, le client paie par facture PayPal ou vers le compte PayPal, quel que soit l'état de FlexPay.
