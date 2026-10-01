# Suivi d'avancement — Supplier Reconciliation

**Dernière mise à jour :** 1 octobre 2026  
**Phase active :** A1 — stabilisation du Free Checker  
**Statut global :** parcours principal implémenté, pas encore prêt pour une publication commerciale

Ce document décrit l'état réel du produit. Il indique ce qui est terminé, partiel, bloqué ou non
commencé. Il doit être mis à jour après chaque changement matériel.

## Légende

- ✅ Terminé et vérifié
- 🟡 Partiel, à valider ou à renforcer
- 🔴 Non commencé
- ⏸️ Délibérément reporté
- ⚠️ Blocage ou dette connue

## Sources de vérité

1. `docs/FUNCTIONAL_SPEC.md` — intention et règles fonctionnelles ;
2. `docs/SUPPLIER_RECONCILIATION_PLAN.md` — architecture et ordre de livraison ;
3. ce document — état courant et prochain travail ;
4. code et tests — preuve de ce qui est réellement implémenté.

En cas de contradiction, vérifier le code, signaler l'écart et remettre les documents en cohérence.

## Résumé exécutif

Le Free Checker possède un parcours de bout en bout : session anonyme, import de deux fichiers,
mapping, contrôle préalable, rapprochement, synthèse, revue humaine et export.

Le moteur est avancé et conservateur. Le produit doit encore être stabilisé dans un environnement
reproductible, testé sur un corpus représentatif et complété par la devise et le contrôle du solde.

Le premier payant n'est pas implémenté. L'authentification du starter kit existe, mais elle ne porte
encore ni clients, ni fournisseurs, ni mappings sauvegardés, ni historique commercial.

## A. Socle mjtools

| Élément                                              | État | Notes                                                                                       |
| ---------------------------------------------------- | ---- | ------------------------------------------------------------------------------------------- |
| Laravel 13, Inertia 3, Vue 3, TypeScript, Tailwind 4 | ✅   | Socle installé                                                                              |
| Architecture modulaire `app/Tools/<Tool>`            | ✅   | SupplierReconciliation est le premier module                                                |
| Authentification Fortify, 2FA et passkeys            | ✅   | Pas encore reliée au produit payant                                                         |
| Page d'accueil listant les outils                    | ✅   | Repositionnée : « petits outils pour vérifications fastidieuses », non limitée à la finance |
| Nom de l'application                                 | ✅   | `mjtools` par défaut (`config/app.php`, `app.ts`) ; `.env` local corrigé                    |
| Traductions                                          | ⏸️   | Interface anglaise et textes en dur; internationalisation prévue plus tard                  |

## B. Import et préparation

| Élément                                         | État | Notes                                                                                                                                                                                                                                        |
| ----------------------------------------------- | ---- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Import CSV                                      | ✅   | Séparateur et encodages courants détectés                                                                                                                                                                                                    |
| Import XLSX                                     | ✅   | OpenSpout installé ; couvert par les tests et le corpus (fichiers XLSX réels)                                                                                                                                                                |
| Refus explicite XLS/PDF/images                  | ✅   | Conforme au périmètre A                                                                                                                                                                                                                      |
| Limites et protection ZIP bomb                  | ✅   | Taille, lignes, colonnes et taille décompressée                                                                                                                                                                                              |
| Détection et modification de la ligne d'en-tête | ✅   | Fonctionnel                                                                                                                                                                                                                                  |
| Mapping des colonnes et valeurs d'exemple       | ✅   | Référence, date, montants, type, description, fournisseur                                                                                                                                                                                    |
| Conventions date, décimales et signes           | ✅   | Confirmables par fichier                                                                                                                                                                                                                     |
| Filtre fournisseur                              | ✅   | Disponible si la colonne est mappée                                                                                                                                                                                                          |
| Fichiers imparfaits courants                    | 🟡   | Corpus de 5 cas fichiers (FR/Sage, US/QuickBooks, UK/Xero, export paginé, pièges) ; corrigés : Charges/Payments, Amount Due, « Total for … », en-têtes répétés, Désignation, pied de page texte ; fichiers réels anonymisés encore à ajouter |
| Choix de feuille XLSX                           | ✅   | Feuille la plus proche d'une liste de transactions par défaut (page de garde ignorée) ; autre feuille au choix en redéposant le fichier (jamais conservé)                                                                                    |
| Référence secondaire                            | 🔴   | Une seule colonne actuellement                                                                                                                                                                                                               |
| Devise unique et garde-fous                     | ✅   | Détectée (codes, symboles, en-tête, colonne Currency), confirmée à l'étape Columns ; toute autre devise bloque ; jamais de conversion ; colonne Currency à l'export                                                                          |
| Contrôle facultatif du solde                    | ✅   | Relevé : solde d'ouverture + lignes = solde de clôture → vérifié / incohérent (écart affiché, avertissement non bloquant) / indisponible ; `BalanceCheckTest`                                                                                |
| PDF texte / OCR                                 | ⏸️   | Périmètre C après validation                                                                                                                                                                                                                 |

## C. Moteur de rapprochement

| Élément                                  | État | Notes                                                                                                                                                      |
| ---------------------------------------- | ---- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Conservation des valeurs originales      | ✅   | Normalisation séparée                                                                                                                                      |
| Références, montants et dates normalisés | ✅   | Montants sans flottants                                                                                                                                    |
| Correspondances exactes et normalisées   | ✅   | Moteur progressif déterministe                                                                                                                             |
| Tolérance de date configurable           | ✅   | Seuils à calibrer sur données réelles                                                                                                                      |
| Candidats et ambiguïtés                  | ✅   | Aucun choix arbitraire                                                                                                                                     |
| Écarts de montant                        | ✅   | Différence explicitée sans jugement comptable                                                                                                              |
| Doublons                                 | ✅   | Statement et ledger                                                                                                                                        |
| One-to-many / many-to-one simple         | ✅   | Proposition, jamais auto-validation                                                                                                                        |
| Avoirs, paiements et signes opposés      | ✅   | Couverts par le moteur et les tests                                                                                                                        |
| Éléments absents et timing difference    | ✅   | Missing in Ledger / Ledger Only                                                                                                                            |
| Explications détaillées                  | ✅   | Raisons, comparaisons et transformations                                                                                                                   |
| Corpus réaliste suffisamment diversifié  | 🟡   | 5 cas étiquetés, 82 lignes, 0 faux auto-match (`CorpusTest`, `php artisan supplier-reconciliation:corpus`) ; à enrichir avec des fichiers réels anonymisés |
| Benchmark contre Excel/IA généraliste    | 🔴   | À créer pour mesurer l'avantage réel                                                                                                                       |

## D. Parcours et résultats

| Élément                                    | État | Notes                                                                                                                                                      |
| ------------------------------------------ | ---- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Démarrage sans compte                      | ✅   | Fonctionnel                                                                                                                                                |
| Upload, mapping et preflight               | ✅   | Fonctionnel                                                                                                                                                |
| Synthèse centrée sur les exceptions        | ✅   | Le pourcentage décrit le travail évité                                                                                                                     |
| Revue filtrée                              | ✅   | Matches masqués par défaut                                                                                                                                 |
| Confirm, Reject, Manual Match, Defer, Undo | ✅   | Décisions séparées du moteur                                                                                                                               |
| Export CSV                                 | ✅   | Protection formula injection                                                                                                                               |
| Export XLSX                                | ✅   | OpenSpout installé ; feuilles Results, To review (éléments ouverts), Summary ; en-tête figé, filtres, largeurs                                             |
| Classeur d'audit enrichi                   | 🟡   | Synthèse avec fichiers, devise, contrôle du solde, décisions séparées du moteur ; dossier d'audit complet réservé au périmètre C                           |
| Jeu d'exemple en un clic                   | ✅   | « Try with sample files » : fournisseur fictif couvrant tous les statuts, téléchargeable ; `SampleRunTest`                                                 |
| Design et confiance visuelle               | ✅   | Direction « Précision comptable » (papier/encre, Source Serif 4, IBM Plex), clair/sombre/mobile vérifiés au navigateur ; retours utilisateurs à recueillir |

## E. Persistance et confidentialité

| Élément                               | État | Notes                                                                                                                                                            |
| ------------------------------------- | ---- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Session anonyme liée au navigateur    | ✅   | Jeton aléatoire haché, en session et dans un cookie chiffré HttpOnly                                                                                             |
| Fichier brut non conservé             | ✅   | Cellules extraites persistées temporairement                                                                                                                     |
| Rétention 24 h et purge               | ✅   | Suppression manuelle disponible                                                                                                                                  |
| Isolation des sessions                | ✅   | Couverture Feature présente                                                                                                                                      |
| Durée session/rétention cohérente     | ✅   | Cookie dédié valable pendant la rétention (24 h) ; date de suppression affichée à chaque étape                                                                   |
| Compteurs sans données comptables     | ✅   | `UsageLog` présent                                                                                                                                               |
| Funnel produit complet                | 🟡   | Intention payante mesurée (clic, questionnaire, prix affiché, email facultatif ; `php artisan supplier-reconciliation:interest`) ; retours qualitatifs à ajouter |
| Chiffrement et isolation multi-tenant | 🔴   | Obligatoires avec le périmètre B                                                                                                                                 |

## F. Qualité et environnement

Dernier contrôle complet (1er octobre 2026, branche `feat/a1-a2-free-checker`) :

- `php artisan test` : 224 tests, 224 réussis (8 911 assertions) ;
- `pint --test`, `phpstan analyse` (niveau 7), `npm run check` et `npm run types:check` : réussis ;
- migration MySQL `supplier_reconciliation_runs` corrigée et exécutée le 30 septembre 2026.

Environnement de référence local (Laragon, PHP 8.3) :

- extensions `pdo_sqlite` et `sqlite3` activées dans `php.ini` (les tests utilisent SQLite en
  mémoire) ;
- `composer install` pour disposer d'OpenSpout dans `vendor` ;
- `npm run build` avant les tests Feature qui rendent une page Inertia : un manifest Vite périmé
  provoque une erreur 500 (`Unable to locate file in Vite manifest`) ;
- les fichiers de skills générés par Laravel Boost (`.agents`, `.claude`, `.cursor`, `.grok`) sont
  exclus du formatage `vp`.

Ces chiffres doivent être remplacés par le prochain résultat complet, pas simplement complétés.

## G. Premier payant — périmètre B

| Élément                                       | État |
| --------------------------------------------- | ---- |
| Conversion d'un run gratuit vers un compte    | 🔴   |
| Workspace                                     | 🔴   |
| Clients et fournisseurs                       | 🔴   |
| Mapping et conventions sauvegardés            | 🔴   |
| Empreinte de structure et détection de dérive | 🔴   |
| Nouvelle période avec mapping précédent       | 🔴   |
| Historique simple et reprise                  | 🔴   |
| Quotas et facturation                         | 🔴   |
| Sécurité et rétention des comptes payants     | 🔴   |

## H. Après validation — périmètre C

| Élément                         | État |
| ------------------------------- | ---- |
| Batch multi-fournisseurs        | ⏸️   |
| Continuité des exceptions       | ⏸️   |
| PDF texte                       | ⏸️   |
| OCR local                       | ⏸️   |
| Réception par email             | ⏸️   |
| Intégrations comptables ciblées | ⏸️   |
| Équipes et audit avancé         | ⏸️   |

## Prochain jalon : Free Checker publiable

1. restaurer un environnement de tests entièrement vert ;
2. constituer un corpus réaliste de fichiers propres et imparfaits ;
3. corriger les défauts révélés sans élargir le périmètre ;
4. ajouter la devise unique et ses blocages ;
5. ajouter le contrôle facultatif du solde ;
6. renforcer l'export et ajouter un jeu d'exemple ;
7. finaliser design, positionnement et textes de confiance ;
8. compléter les mesures et le CTA « sauvegarder ce fournisseur » ;
9. publier et observer avant de commencer le périmètre B complet.

## Définition de « terminé » pour le jalon actuel

- suite de tests verte dans l'environnement documenté ;
- aucun faux auto-match dans le corpus étiqueté ;
- devise confirmée ou blocage explicite ;
- solde marqué vérifié, incohérent ou indisponible ;
- parcours démontrable avec un jeu d'exemple ;
- export exploitable ;
- confidentialité et suppression visibles avant l'upload ;
- métriques du parcours et de l'intention payante disponibles ;
- aucun élément du périmètre B ou C requis pour publier.

## Journal des changements

| Date       | Changement                                                                                                | Preuve                                                               |
| ---------- | --------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------- |
| 2026-10-02 | Choix de la feuille XLSX (automatique + manuel)                                                           | `FileImporterTest`, `ReconciliationFlowTest` ; 290/290               |
| 2026-10-02 | Export XLSX renforcé : liste « To review », synthèse avec contexte, mise en forme                         | `ResultExporterTest`                                                 |
| 2026-10-02 | Corpus étiqueté de fichiers réalistes + mesure (`supplier-reconciliation:corpus`) et corrections révélées | `CorpusTest` ; 284/284 ; 0 faux auto-match                           |
| 2026-10-02 | CTA « Save this supplier » : mesure de l'intention payante                                                | `InterestTest` ; navigateur                                          |
| 2026-10-02 | Accès au rapprochement aligné sur la rétention (cookie 24 h)                                              | Test Feature cookie/session ; 257/257                                |
| 2026-10-02 | Contrôle facultatif du solde du relevé (check + synthèse)                                                 | `BalanceCheckTest`, `SampleRunTest` ; 256/256                        |
| 2026-10-02 | Devise unique par rapprochement (détection, confirmation, blocage)                                        | `CurrencyCheckTest`, `CurrencyDetectorTest`, tests Feature ; 251/251 |
| 2026-10-01 | Design system mjtools, refonte de l'accueil et du parcours                                                | Vérification navigateur clair/sombre/mobile, vp check, vue-tsc       |
| 2026-10-01 | Jeu d'exemple en un clic + correction pied de page texte                                                  | `SampleRunTest`, `MappingTest`                                       |
| 2026-10-01 | Environnement de tests rétabli : suite complète verte                                                     | 224/224, pint, phpstan, vp check, vue-tsc                            |
| 2026-10-01 | Création du suivi et séparation des périmètres A/B/C                                                      | Spec et plan mis à jour                                              |
| 2026-09-30 | Migration MySQL corrigée (`TIMESTAMP` → `DATETIME`)                                                       | `php artisan migrate` réussi                                         |

## Règle de maintenance

Toute modification matérielle met à jour la ligne concernée, la date, le journal et la preuve
utilisée. Ne jamais marquer un élément ✅ uniquement parce que le code existe : il doit avoir été
vérifié à un niveau proportionné au risque.
