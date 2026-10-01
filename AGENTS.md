# Point d'entrée des agents — mjtools

Ce fichier est l'entrée obligatoire de toute IA ou de tout agent qui analyse, modifie ou poursuit le
développement de ce repository.

## Lecture obligatoire avant d'agir

Lire intégralement, dans cet ordre :

1. `docs/README.md` ;
2. `docs/FUNCTIONAL_SPEC.md` ;
3. `docs/IMPLEMENTATION_STATUS.md` ;
4. `docs/SUPPLIER_RECONCILIATION_PLAN.md` ;
5. les fichiers de code et tests directement concernés par la tâche.

Ne pas commencer une implémentation à partir du seul nom d'une fonctionnalité.

## Hiérarchie de décision

1. demande explicite actuelle de l'utilisateur ;
2. cahier des charges fonctionnel ;
3. plan d'implémentation ;
4. suivi d'avancement ;
5. conventions établies par le code voisin et les tests.

Si deux niveaux se contredisent, signaler le conflit et proposer la mise à jour appropriée. Ne pas
inventer une nouvelle direction produit pour débloquer une tâche locale.

## Contexte produit

`mjtools` est un socle modulaire destiné à plusieurs micro-outils indépendants. Supplier
Reconciliation est le premier outil, pas le positionnement global définitif de la plateforme.

La cible initiale est constituée des bookkeepers, petits cabinets et petites équipes AP qui répètent
le rapprochement sur plusieurs fournisseurs et périodes sans vouloir une solution enterprise lourde.

Principes produit :

> Accepter les données imparfaites. Ne jamais masquer l'incertitude. Expliquer chaque résultat.

Le Free Checker démontre la valeur. Le premier payant mémorise le client, le fournisseur, son mapping
et son historique afin que l'utilisateur ne reparte pas de zéro à la période suivante.

## Portée actuelle

La phase active est **A1/A2 : stabiliser et compléter le Free Checker**.

Ne pas commencer spontanément les comptes produit, mappings payants sauvegardés, batch, PDF/OCR,
email entrant ou intégrations comptables. Ces éléments appartiennent aux périmètres B ou C et exigent
une demande explicite ou le signal produit défini dans les documents.

## Contraintes non négociables

- aucune API d'IA payante dans le chemin principal du produit ;
- matching déterministe, prudent et reproductible ;
- aucune correspondance forcée en cas d'ambiguïté ;
- originaux toujours conservés séparément des valeurs normalisées ;
- décisions du moteur et décisions humaines toujours distinguées ;
- aucune modification de la comptabilité source ;
- aucune donnée comptable sensible dans les logs ou métriques ;
- ne pas transformer l'outil en ERP, système de paiement ou suite AP généraliste.

La « mémoire » du produit signifie mappings, conventions, structure et décisions sauvegardés. Elle ne
signifie pas machine learning.

## Architecture

- plateforme partagée : `app/Http`, `app/Models`, `app/Providers`, auth, settings et UI générique ;
- outil : `app/Tools/SupplierReconciliation` ;
- pages : `resources/js/pages/tools/supplier-reconciliation` ;
- composants/types : `resources/js/tools/supplier-reconciliation` ;
- tests : `tests/Unit/Tools/SupplierReconciliation` et `tests/Feature/Tools/SupplierReconciliation`.

`Domain`, `Normalization`, `Matching`, `Result` et `Review` restent en PHP pur. Ne pas y introduire
Illuminate, Inertia, OpenSpout ou une dépendance HTTP. Les fichiers, Laravel et l'UI restent dans les
couches externes.

Un nouvel outil doit rester isolé dans `app/Tools/<Tool>` et ne doit pas créer une dépendance du socle
vers un module métier.

## Règles de travail

1. Inspecter `git status` et préserver toutes les modifications existantes.
2. Lire les fichiers voisins et suivre les conventions déjà établies.
3. Faire le plus petit changement cohérent avec le périmètre demandé.
4. Ajouter ou ajuster les tests proportionnellement au risque.
5. Exécuter d'abord les tests ciblés, puis les contrôles plus larges pertinents.
6. Ne jamais masquer une erreur d'environnement en déclarant une fonctionnalité valide.
7. Ne pas ajouter une dépendance, un service payant ou une API sans décision explicite.
8. Mettre à jour `docs/IMPLEMENTATION_STATUS.md` après toute avancée matérielle.
9. Mettre à jour le cahier des charges si le comportement fonctionnel attendu change.
10. Mettre à jour le plan si l'architecture ou l'ordre de livraison change.

## Vérifications usuelles

```text
php artisan test <tests ciblés>
php vendor/bin/pint --test
vendor/bin/phpstan analyse
npm run check
npm run types:check
```

Ne pas affirmer que le projet est vert si une partie des contrôles n'a pas pu être exécutée.

## Problèmes d'environnement connus

Les tests utilisent SQLite en mémoire (`pdo_sqlite` requis), OpenSpout doit être installé via
`composer install`, et les tests Feature qui rendent une page Inertia exigent un manifest Vite à
jour (`npm run build`). Le détail et le dernier résultat complet sont dans
`docs/IMPLEMENTATION_STATUS.md` (section F).

## Langue et textes

- documentation produit : français ;
- interface actuelle : anglais avec textes largement en dur ;
- traduction : reportée, sans multiplier inutilement les textes dispersés ;
- ne pas mélanger français et anglais dans une même interface sans décision explicite.

## Condition de fin d'une tâche

Une tâche n'est terminée que lorsque le comportement demandé existe réellement, les vérifications
pertinentes ont été exécutées ou leurs limites signalées, les changements non liés sont préservés et
le suivi d'avancement reflète la nouvelle réalité.
