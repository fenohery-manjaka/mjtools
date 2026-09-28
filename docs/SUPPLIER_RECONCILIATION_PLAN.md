# Plan d'implémentation — Supplier Statement Reconciliation Checker V1

**Source de vérité fonctionnelle :** `docs/FUNCTIONAL_SPEC.md` (en cas de conflit, la spec gagne).
**Portée :** V1 uniquement. Tout ce que la spec classe hors V1 (§46) est exclu.

---

## 1. État initial du repository

- Laravel 13, PHP ^8.3 (CI en 8.3), Inertia v3 + Vue 3 + TypeScript, Tailwind v4, composants
  shadcn-vue (reka-ui) dans `resources/js/components/ui`, Wayfinder (routes typées), Fortify
  (auth, 2FA, passkeys).
- Tests PHPUnit 12 (`tests/Unit`, `tests/Feature`), Pint (preset laravel), Larastan niveau 7,
  `vue-tsc`, `vp check` (lint/format front).
- Aucune fonctionnalité métier : c'est le starter kit Vue de Laravel.

Ce socle (auth, settings, layouts, composants UI) **devient le socle partagé de mjtools** et
n'est pas réorganisé.

## 2. Architecture globale mjtools : monolithe modulaire multi-outils

```
app/
  Http/, Models/, Actions/, Providers/ ...   ← socle partagé (Platform) — inchangé
  Tools/
    SupplierReconciliation/                  ← premier outil, module isolé
      SupplierReconciliationServiceProvider.php
      ...
resources/js/
  layouts/ToolLayout.vue                     ← layout public commun aux outils (socle)
  pages/tools/supplier-reconciliation/*.vue  ← pages Inertia de l'outil
  tools/supplier-reconciliation/             ← composants/types propres à l'outil
tests/
  Unit/Tools/SupplierReconciliation/...
  Feature/Tools/SupplierReconciliation/...
```

**Règles de frontière**

| Socle partagé (Platform) | SupplierReconciliation |
|---|---|
| auth, users, settings, layouts, composants UI génériques, page d'accueil listant les outils | import, mapping, normalisation, moteur, résultats, revue, export, persistance des sessions de rapprochement |

- Un outil = un dossier `app/Tools/<Tool>` + **un** service provider enregistré dans
  `bootstrap/providers.php`. Le provider charge ses routes, ses migrations, ses commandes et sa
  planification ; sa configuration est dans `config/<tool>.php`. Ajouter un outil ne touche pas aux autres.
- Le socle ne dépend jamais d'un outil. Un outil peut dépendre du socle (layout, UI).
- Pas de registre d'outils dynamique, pas de système de plugins : la page d'accueil liste les
  outils en dur (un seul aujourd'hui). On généralisera quand un deuxième outil existera.
- Tables préfixées par l'outil (`supplier_reconciliation_*`), routes nommées
  `supplier-reconciliation.*`, URL `/tools/supplier-reconciliation/...`, clé de config
  `supplier-reconciliation`.

## 3. Structure interne du module

```
app/Tools/SupplierReconciliation/
  SupplierReconciliationServiceProvider.php, routes.php   (config : config/supplier-reconciliation.php)
  database/migrations/
  Domain/          Transaction (modèle canonique), Side, DocumentType, Amount, CalendarDate
  Normalization/   ReferenceNormalizer, AmountParser, DateParser, formats, TransactionBuilder
  Matching/        ReconciliationEngine, MatchingPolicy, PairEvidence + comparateurs,
                   CandidateFinder, Stages/ (une classe par règle)
  Result/          ReconciliationResult, ResultItem, ItemStatus, Reason, Summary...
  Review/          Decision, ReviewApplier (superposition des décisions humaines)
  Import/          CsvReader, XlsxReader, FileImporter, HeaderDetector, RawTable, erreurs
  Mapping/         Field, ColumnMapping, ColumnDetector, format detection, PreflightCheck
  Export/          ResultExporter (CSV / XLSX)
  Runs/            ReconciliationRun (Eloquent), accès par session, purge
  Http/            Controllers, Requests, Presenters (transformation vers props Inertia)
```

**Dépendances (sens unique)**

```
Http ─► Runs ─► Import ─► Mapping ─► Normalization ─► Domain
  │                                                   ▲
  ├──► Matching ─► Result ────────────────────────────┘
  ├──► Review ─► Result
  └──► Export ─► Review/Result
```

`Domain`, `Normalization`, `Matching`, `Result`, `Review` sont du **PHP pur** : aucune
dépendance à Laravel, HTTP, Inertia, Vue, CSV ou XLSX. Un test d'architecture le vérifie.
Remplacer l'import fichier par une source Xero/QuickBooks = produire des `Transaction`
autrement ; le moteur ne change pas.

## 4. Flux

```
Fichier ─► Import (RawTable) ─► détection en-têtes + mapping proposé ─► confirmation utilisateur
       ─► TransactionBuilder (valeurs originales + normalisées + anomalies de lecture)
       ─► Preflight (contrôle avant analyse : bloquant / avertissements)
       ─► ReconciliationEngine ─► ReconciliationResult (items + raisons + synthèse)
       ─► ReviewApplier(décisions humaines) ─► UI / Export
```

## 5. Import et validation (spec §5, §7, §8, §37)

- Formats : CSV (séparateur `,` `;` tab `|` détecté ; encodage UTF-8/UTF-8 BOM/UTF-16/Windows-1252),
  XLSX (première feuille non vide, via `openspout/openspout`, streaming).
- XLS / PDF / images : refus explicite avec message (« enregistrez en XLSX ou CSV »).
- Détection du type par contenu (signature ZIP / OLE / PDF), pas seulement par extension.
- Limites (config) : 10 Mo, 10 000 lignes, 100 colonnes, taille décompressée XLSX bornée
  (anti zip-bomb).
- Erreurs expliquées : fichier vide, corrompu, aucun tableau exploitable, trop volumineux.
- Le fichier brut n'est **jamais stocké** : il est lu depuis le fichier temporaire d'upload ;
  seules les cellules extraites sont conservées (texte), pour la durée de rétention.
- Ligne d'en-têtes détectée automatiquement (score mots-clés + forme des données), modifiable.

## 6. Mapping (spec §9, §10, §38)

- Champs : `reference`, `date`, `amount` **ou** `debit`+`credit`, optionnels `type`,
  `description`, `supplier` (ledger).
- Proposition automatique (mots-clés EN/FR + contenu), toujours modifiable ; chaque champ montre
  des valeurs d'exemple.
- Conventions explicites par fichier, proposées puis confirmées :
  - format de date (auto / JJ/MM / MM/JJ / AAAA-MM-JJ), ambiguïté signalée ;
  - séparateur décimal (auto / point / virgule) ;
  - **convention de signe** : les factures apparaissent en positif ou en négatif (montant
    signé), ou dans la colonne Débit ou Crédit. Toutes les valeurs normalisées sont exprimées
    dans la perspective du relevé fournisseur (facture > 0, avoir/paiement < 0). Une inversion est
    une règle de fichier explicite et visible, jamais un ajustement ligne à ligne ;
  - option « le signe vient de la colonne Type » quand les avoirs sont listés en positif.
- Filtre fournisseur optionnel si le ledger contient plusieurs fournisseurs.
- Contrôle avant analyse : lignes par fichier, champs ✓, lignes illisibles, conventions
  appliquées, **suggestion d'inversion de signe** si les références communes ont
  majoritairement des signes opposés. Bloquant si champs requis absents ou trop de lignes
  illisibles (« Review required before reconciliation »).

## 7. Modèle canonique et normalisation (spec §11–13, §39)

`Transaction` : id stable (`S12`, `L40`), côté, numéro de ligne d'origine, valeurs **originales**
(référence, date, montant/débit/crédit, type, description) + valeurs **normalisées** + liste des
anomalies de lecture. Les originaux ne sont jamais modifiés.

- **Référence** → `NormalizedReference` : original, clé typographique (majuscules, sans espaces
  ni séparateurs/ponctuation), clé sans zéros de tête dans les blocs numériques, partie
  lettres, cœur numérique, indicateur « identifiante » (contient un chiffre, longueur ≥ 3), et
  étapes appliquées (pour l'explication).
- **Montant** → `Amount` entier à l'échelle 10⁻⁴ (pas de flottants). Gère `1 240,00`,
  `1,240.00`, `1.240,00`, `1'240.00`, espaces insécables, symboles monétaires, `(1 240,00)`,
  `-`, `1240-`, suffixes `CR`/`DR`. Ambiguïtés (`1,240` seul) résolues par la colonne entière,
  sinon anomalie. Le signe n'est jamais supprimé.
- **Date** → `CalendarDate` : `JJ/MM/AAAA`, `MM/JJ/AAAA`, ISO, `JJ-MM-AAAA`, `JJ.MM.AAAA`,
  années sur 2 chiffres, mois en lettres (EN/FR), dates-heures, cellules date XLSX.
- **Type de document** : Invoice / Credit / Payment / Unknown, déduit de la colonne Type,
  sinon du préfixe de référence (CN, AV...), sinon du signe.
- Lignes de solde/total (`Balance b/f`, `Total`...) détectées par mots-clés stricts et
  **exclues** du rapprochement, mais visibles (statut `Excluded`).

## 8. Moteur de rapprochement (spec §14–26, §33, §47, §48)

Principes : déterministe (ordre stable, aucune dépendance à l'aléatoire ou à l'ordre de hash),
conservateur, progressif du plus sûr au moins sûr, chaque élément retiré une fois résolu.

### Preuves par paire (`PairEvidence`)

- Relation de référence : `Identical` > `Formatting` (casse/espaces/séparateurs) >
  `LeadingZeros` > `PrefixMissing` (`INV-004583` ↔ `4583`) > `PrefixDifferent` >
  `Similar` (1 faute de frappe/transposition) > `Different` / `Unavailable`.
  Deux préfixes alphabétiques différents (INV vs CN) ne sont jamais « formatting ».
- Relation de montant : égal / écart (Δ) / signe opposé / indisponible.
- Écart de date en jours / indisponible.
- Un score interne ordonne les candidats ; il n'est **jamais affiché**.

Les candidats sont générés par index (clés de référence, montant) — pas de produit cartésien.

### Étapes (une classe par règle, ordre fixe)

1. **Données insuffisantes** → `Review Required` (montant illisible ; ni référence
   identifiante ni date ; signe indéterminé). Lignes de solde → `Excluded`.
2. **Doublons** (même côté, même clé de référence, même montant) → `Duplicate Suspected`
   avec les lignes de l'autre côté concernées ; sauf si leur somme égale exactement une ligne
   unique de l'autre côté → proposition groupée (`Possible Match`).
3. **Rapprochements certains** → `Matched` (`Exact` : référence, montant et date identiques ;
   `Normalized` : différences de format / zéros / date tolérée). Conditions cumulatives :
   référence identifiante, relation ≤ `LeadingZeros`, montant égal, écart de date ≤ seuil
   (14 j ; 7 j si zéros retirés), et **unicité mutuelle** : ni l'une ni l'autre ligne n'a un
   autre candidat de même montant lié par la référence, quel que soit le niveau (spec §19).
4. **Groupes simples** one-to-many / many-to-one par référence commune, somme exacte,
   2 à 5 lignes, même signe → `Possible Match` groupé, jamais validé automatiquement (§20).
5. **Écarts de montant** : référence forte unique des deux côtés, même sens, montants
   différents → `Amount Mismatch` (Δ affiché, aucun montant « correct » choisi). Même montant
   mais signe opposé → `Review Required`. Plusieurs lignes même référence sans somme
   cohérente → `Ambiguous`.
6. **Candidats** (montant égal obligatoire), classes :
   A. référence liée (tous niveaux) ; B. référence absente/non identifiante + date ≤ 7 j ;
   C. références différentes + date ≤ 3 j (proposition seulement si unique).
   Pour chaque ligne on retient sa meilleure classe ; composantes connexes des arêtes
   mutuellement retenues : 1↔1 → `Possible Match` ; plus → `Ambiguous` (tous les candidats
   listés), jamais de choix arbitraire.
7. **Restants** → `Missing in Ledger` (avec mention avoir / paiement) ou `Ledger Only`, et
   indication « Possible timing difference » près de la fin de période de l'autre fichier (§26).

### Explicabilité (§31, §32)

Chaque `ResultItem` porte : statut, sous-type, catégorie de confiance (`certain`,
`strong_candidate`, `ambiguous`, `no_match`), transactions liées, candidats, **raisons produites
par le moteur** (code, polarité ✓/~/✗, message) et comparaison champ par champ
(original ↔ normalisé des deux côtés, transformations appliquées). L'UI ne fait qu'afficher.

## 9. Classification et synthèse (§21, §27–29)

Statuts : `Matched`, `Possible Match`, `Ambiguous`, `Review Required`, `Missing in Ledger`,
`Ledger Only`, `Amount Mismatch`, `Duplicate Suspected`, `Excluded`.

Synthèse : lignes analysées (relevé + ledger, hors exclues), lignes rapprochées
automatiquement, **éléments nécessitant attention**, pourcentage « cleared automatically »
(réduction de la revue, jamais « précision »), montants par catégorie (manquants — factures /
avoirs séparés —, écarts, ledger only) sans total trompeur.

## 10. Revue humaine et traçabilité (§34, §35)

Décisions stockées séparément du résultat moteur (jamais fusionnées) :
`confirm`, `reject`, `match` (manuel, ou choix d'un candidat), `defer`, avec annulation.
`ReviewApplier` (pur) produit la vue effective : statut moteur, décision humaine, statut final.
Une confirmation humaine n'est jamais présentée comme automatique.

## 11. Persistance, confidentialité, sécurité (§40)

- Pas de compte requis. Une session de rapprochement (`supplier_reconciliation_runs`, ULID)
  est liée à la session navigateur (jeton aléatoire stocké en session, comparé en temps
  constant). Accès refusé (404) sinon.
- Données conservées : cellules extraites, mapping, résultat, décisions. Suppression
  automatique après 24 h (commande planifiée), suppression immédiate possible par l'utilisateur.
- Uploads : taille/extension/contenu validés, fichiers jamais écrits sur disque applicatif,
  limitation de débit, export CSV protégé contre l'injection de formules.

## 12. UI (§6, §27, §30, §31)

Parcours : Accueil outil → Fichiers (relevé, ledger : nom, type, lignes, aperçu) → Mapping →
Contrôle → Rapprochement → **Synthèse** (moment de valeur) → Revue des exceptions (filtres,
comparaison côte à côte, « Why we linked these », actions) → Export.
Les rapprochés automatiques sont masqués par défaut mais consultables. Toute logique vient du
serveur (libellés, raisons, actions autorisées).

## 13. Export (§36)

CSV et XLSX : une ligne par transaction liée — statut final, statut moteur, sous-type,
décision humaine, lignes relevé/ledger (originaux), écart, raisons. Aucun matching à l'export.

## 14. Tests

- Unitaires purs : normaliseurs (références, montants, dates), comparateurs, chaque étape,
  moteur complet sur **jeux de données réalistes** (propres, imparfaits, formats, doublons,
  avoirs, écarts, absents, ambiguïtés, pièges à faux positifs) — avec un test « aucun faux
  auto-match » sur un jeu étiqueté, et tous les cas obligatoires §47.
- Import : CSV (séparateurs, encodages, BOM), XLSX (générés en test), erreurs.
- Mapping : détection, validation, preflight.
- Revue / export.
- Feature : parcours HTTP complet, isolation par session, erreurs d'upload, purge.
- Architecture : le moteur n'importe ni Illuminate, ni Inertia, ni OpenSpout.

## 15. Ordre d'implémentation

1. Domain + Normalization (+ tests) → 2. Result + Matching (+ datasets) → 3. Import + Mapping →
4. Review + Export → 5. Runs + HTTP + provider → 6. UI → 7. Revue finale.

## 16. Décisions

| Décision | Raison |
|---|---|
| `openspout/openspout ~5.3.0` pour XLSX | MIT, streaming, léger ; 5.3 est la dernière branche compatible PHP 8.3 (CI) |
| Pas de support XLS | spec : optionnel ; complexité disproportionnée — message explicite |
| Montants en entiers ×10⁴ | pas d'erreur d'arrondi flottant, comparaison exacte |
| Seuils de date configurables (14 j / 7 j / 3 j) | spec §17 : à calibrer ; valeurs prudentes par défaut |
| Références non identifiantes (sans chiffre) jamais auto-rapprochées | `PAYMENT` ↔ `PAYMENT` n'identifie pas un document |
| Stockage en base (JSON) 24 h, pas de fichier brut conservé | confidentialité §40, simplicité |
| Migrations et routes dans le module, config dans `config/supplier-reconciliation.php` | frontière d'outil réelle ; config au standard Laravel (compatible `config:cache`) |
