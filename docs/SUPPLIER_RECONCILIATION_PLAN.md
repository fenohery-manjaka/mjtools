# Plan d'implémentation — Supplier Reconciliation

**Source de vérité fonctionnelle :** `docs/FUNCTIONAL_SPEC.md` (en cas de conflit, la spec gagne).
**État :** le cœur du périmètre A (Free Checker) est implémenté ; ce document décrit désormais sa
finalisation puis la livraison progressive des périmètres B et C définis au §4 de la spec.

**Règle de portée :** le périmètre C ne constitue jamais un prérequis à la publication du Checker ou
à la première vente. Chaque phase doit obtenir son signal produit avant d'élargir la suivante.

---

## 1. État initial du repository

- Laravel 13, PHP ^8.3 (CI en 8.3), Inertia v3 + Vue 3 + TypeScript, Tailwind v4, composants
  shadcn-vue (reka-ui) dans `resources/js/components/ui`, Wayfinder (routes typées), Fortify
  (auth, 2FA, passkeys).
- Tests PHPUnit 12 (`tests/Unit`, `tests/Feature`), Pint (preset laravel), Larastan niveau 7,
  `vue-tsc`, `vp check` (lint/format front).
- Aucune fonctionnalité métier : c'est le starter kit Vue de Laravel.

Ce socle (auth, settings, layouts, composants UI) **devient le socle partagé de mjtools** et
n'est pas réorganisé. `mjtools` est une plateforme générique de micro-outils indépendants ; le
rapprochement fournisseur est son premier outil, pas le positionnement définitif de toute la
plateforme.

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

| Socle partagé (Platform)                                                                    | SupplierReconciliation                                                                                      |
| ------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
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
  XLSX (via `openspout/openspout`, streaming) : la feuille contenant le plus de lignes « date +
  nombre » est lue par défaut (pages de garde et synthèses ignorées) ; l'utilisateur peut demander
  une autre feuille en redéposant le fichier, qui n'est jamais conservé.
- XLS / PDF / images : refus explicite avec message (« enregistrez en XLSX ou CSV »).
- Détection du type par contenu (signature ZIP / OLE / PDF), pas seulement par extension.
- Limites (config) : 10 Mo, 5 000 lignes, 100 colonnes, taille décompressée XLSX bornée
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
- **Colonne référence par recoupement** : quand un fichier contient un numéro interne et le
  numéro du fournisseur, `ReferenceColumnAdvisor` propose la colonne qui partage nettement plus de
  références avec l'autre fichier (au moins 2 et deux fois plus que la colonne actuelle). Appliqué
  à l'import (et au premier fichier tant que son mapping n'a pas été modifié), signalé au contrôle
  avant analyse sinon. Une colonne Running balance n'est proposée que si ses variations suivent les
  montants des lignes.
- **Devise unique** (A2) : `CurrencyDetector` lit les codes ISO et symboles des montants, une
  colonne Currency optionnelle et l'en-tête de la colonne montant (« Amount (GBP) ») ; un symbole
  ambigu (`$`) reste compatible avec plusieurs codes. La devise est proposée puis confirmée pour
  tout le rapprochement (`supplier_reconciliation_runs.currency`). Le contrôle avant analyse bloque
  si elle n'est pas confirmée ou si une ligne ou un fichier indique une autre devise. Aucune
  conversion.
- **Contrôle du solde** (A2, facultatif) : `BalanceCheck` additionne les lignes du relevé entre
  la ligne de solde précédant la première transaction (zéro sinon) et la dernière ligne de solde
  suivant la dernière transaction. Résultat : vérifié, incohérent (écart affiché, simple
  avertissement) ou indisponible (pas de solde de clôture, montant illisible). Jamais bloquant.
  Une colonne de solde progressif (Running balance) facultative fournit les soldes lorsqu'ils ne
  sont pas dans les colonnes de montant, ou encadre la période en l'absence de lignes de solde.
  Le rapprochement des soldes relevé/ledger reste une évolution possible.
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
  est liée au navigateur (jeton aléatoire stocké en session et dans un cookie chiffré HttpOnly
  valable pendant la rétention, comparé en temps constant). Accès refusé (404) sinon.
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
Le classeur XLSX contient trois feuilles : _Results_ (toutes les lignes, filtres et en-tête
figé), _To review_ (uniquement les éléments encore ouverts, la liste de travail) et _Summary_
(fichiers, devise, contrôle du solde, chiffres du moteur, décisions humaines séparées, montants
par catégorie sans total trompeur). Une colonne _Currency_ figure dans les deux formats.

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
- **Corpus de fichiers étiquetés** (`tests/Fixtures/SupplierReconciliation/corpus`) : chaque cas
  contient un relevé, un ledger et `expected.json` (vraies paires par numéro de ligne, statuts
  attendus, statut du contrôle de solde). `Corpus/CorpusRunner` les fait passer par le même chemin
  que le produit, sans correction humaine ; `CorpusTest` exige zéro faux auto-match et
  `php artisan supplier-reconciliation:corpus [dossier]` mesure un corpus, y compris de vrais
  fichiers anonymisés rangés au même format hors du dépôt.

## 15. Ordre de livraison à partir de l'état actuel

### Phase A1 — Stabiliser le Free Checker

1. rendre toute la suite de tests exécutable dans l'environnement de référence ;
2. corriger les défauts d'import, de mapping et de matching révélés par des fichiers réalistes ;
3. constituer un corpus étiqueté de fichiers propres et imparfaits ;
4. mesurer les faux auto-matchs, le taux de lignes éliminées et les erreurs d'import ;
5. améliorer l'export et fournir un jeu de données d'exemple.

### Phase A2 — Compléter la promesse avant publication

1. ajouter une devise unique par rapprochement comme garde-fou, sans conversion ;
2. ajouter un contrôle facultatif du solde lorsque les données le permettent ;
3. renforcer les diagnostics de fichiers imparfaits et les contrôles avant analyse ;
4. présenter clairement confidentialité, rétention et suppression ;
5. instrumenter le CTA d'intention « sauvegarder ce fournisseur » sans construire tout le SaaS.

### Phase B — Premier payant minimal

1. compte et espace de travail ;
2. structure simple client/fournisseur ;
3. sauvegarde du mapping et des conventions après un rapprochement réussi ;
4. empreinte de structure et détection de dérive avant réutilisation ;
5. nouvelle période, reprise, historique simple et exports ;
6. isolation, chiffrement, rétention et suppression des données persistantes ;
7. quotas, facturation et gestion autonome de l'abonnement.

### Phase C — Uniquement après validation commerciale

1. batch multi-fournisseurs ;
2. continuité des exceptions entre périodes ;
3. dossier d'audit enrichi ;
4. PDF texte avec validation de l'extraction ;
5. réception par email ;
6. OCR local si la demande le justifie ;
7. intégrations comptables choisies à partir des usages observés.

## 16. Décisions

| Décision                                                                              | Raison                                                                            |
| ------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| `openspout/openspout ~5.3.0` pour XLSX                                                | MIT, streaming, léger ; 5.3 est la dernière branche compatible PHP 8.3 (CI)       |
| Pas de support XLS                                                                    | spec : optionnel ; complexité disproportionnée — message explicite                |
| Montants en entiers ×10⁴                                                              | pas d'erreur d'arrondi flottant, comparaison exacte                               |
| Seuils de date configurables (14 j / 7 j / 3 j)                                       | spec §17 : à calibrer ; valeurs prudentes par défaut                              |
| Références non identifiantes (sans chiffre) jamais auto-rapprochées                   | `PAYMENT` ↔ `PAYMENT` n'identifie pas un document                                 |
| Stockage en base (JSON) 24 h, pas de fichier brut conservé                            | confidentialité §40, simplicité                                                   |
| Migrations et routes dans le module, config dans `config/supplier-reconciliation.php` | frontière d'outil réelle ; config au standard Laravel (compatible `config:cache`) |
| Aucune API d'IA payante dans le chemin principal                                      | coût prévisible, confidentialité, reproductibilité et absence de dépendance       |
| « Mémoire » = données structurées, pas machine learning                               | mappings, conventions et décisions sont explicitement sauvegardés                 |
| Une devise par rapprochement avant le multi-devise                                    | empêcher les comparaisons incohérentes sans introduire de conversion              |
| Contrôle de solde facultatif                                                          | tous les relevés ne contiennent pas un solde initial et final vérifiables         |

## 17. État actuel du périmètre A et limites connues

- Implémenté : tout le parcours §6–§36 (voir tests `tests/Unit/Tools/SupplierReconciliation`
  et `tests/Feature/Tools/SupplierReconciliation`).
- Stockage du résultat en JSON par lignes (`Runs/ResultCodec`) : une requête reste sous
  ~95 Mo de mémoire à la limite de 2 × 5 000 lignes.
- Signaux d'usage (§49) : `Runs/UsageLog` journalise des compteurs uniquement
  (`supplier-reconciliation.*` dans les logs), jamais de références ni de montants.
- Intention payante (§43) : après la synthèse, « Save this supplier » enregistre le clic
  (`UsageLog`) puis un questionnaire court (fournisseurs par mois, logiciel comptable, réponse au
  prix affiché `supplier-reconciliation.paid_intent.price`, email facultatif) dans
  `supplier_reconciliation_interest`, sans lien avec un rapprochement ni donnée comptable.
  Synthèse : `php artisan supplier-reconciliation:interest`. Rien n'est vendu à ce stade.
- Limites connues :
    - une seule colonne de référence utilisée à la fois (choisie par recoupement, pas de
      comparaison simultanée de deux colonnes) ;
    - « formatting » considère `INV-12-3` et `INV-123` comme identiques (séparateurs ignorés) ;
    - pas de multi-devises, pas de XLS ;
    - seuils de dates non encore calibrés sur des données réelles.

Les éléments A1/A2 sont livrés (voir `IMPLEMENTATION_STATUS.md`). Il reste, avant publication :

- valider le moteur sur des fichiers réels anonymisés (`php artisan supplier-reconciliation:corpus
<dossier>`), et calibrer les seuils de dates si nécessaire ;
- relire le parcours et les textes avec un regard humain ;
- publier, puis observer les signaux (`supplier-reconciliation:interest`, journaux d'usage).

## 18. Architecture du premier payant minimal — périmètre B

Le périmètre B étend le module sans déplacer la logique de rapprochement existante. Le moteur pur
continue à recevoir des `Transaction`; les fonctionnalités payantes orchestrent les fichiers,
mappings, périodes et droits d'accès autour de lui.

Modèle fonctionnel minimal :

```text
Workspace
  └── Client
        └── Supplier
              ├── MappingProfile + StructureFingerprint
              └── ReconciliationPeriod
                    └── Run + Decisions + Exports
```

Règles :

- un rapprochement gratuit peut être sauvegardé au moment où l'utilisateur crée son compte ;
- le mapping réutilisé reste une proposition : une dérive de structure impose une confirmation ;
- la persistance payante ne réutilise pas le jeton de session anonyme comme mécanisme d'autorisation ;
- les données d'un workspace ne sont accessibles qu'à ses membres autorisés ;
- le résultat du moteur, la décision humaine et le statut final restent séparés ;
- un utilisateur peut supprimer un fournisseur, un rapprochement ou son espace selon les règles de
  rétention applicables ;
- aucune fonction du périmètre B ne dépend d'une API d'IA externe.

Le premier payant est livrable sans batch, PDF, email ni intégration comptable.

## 19. Critère de passage au périmètre C

Le périmètre C ne commence que si les données montrent au moins un signal commercial crédible :

- utilisateurs revenant sur une nouvelle période ;
- mappings sauvegardés effectivement réutilisés ;
- créations de compte après un résultat réussi ;
- intention de paiement ou premiers paiements ;
- demande répétée et identifiable pour le batch, le PDF ou une intégration donnée.

La prochaine capacité est choisie par le principal obstacle observé :

- nombreux fournisseurs par utilisateur → batch ;
- nombreux fichiers refusés car PDF → PDF texte ;
- exceptions récurrentes difficiles à suivre → continuité des exceptions ;
- même logiciel comptable dominant → intégration ciblée.

## 20. Non-objectifs permanents ou lointains

Le produit reste spécialisé dans le rapprochement et la préparation de la revue. Ne pas transformer
le module en ERP, solution de paiement, système d'approbation de factures, outil de résolution de
litiges, chatbot comptable ou suite Accounts Payable généraliste.
