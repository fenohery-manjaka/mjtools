# CAHIER DES CHARGES FONCTIONNEL

## Supplier Reconciliation — Free Checker et produit récurrent

**Statut :** Document fonctionnel de référence  
**Porte d'entrée :** Free Supplier Statement Reconciliation Checker  
**Premier produit payant :** workflow récurrent de rapprochement par client et fournisseur  
**Évolution visée :** automatisation progressive centrée sur les exceptions

---

# 0. POSITIONNEMENT DE MJTOOLS

`mjtools` n'est pas une plateforme réservée aux équipes financières. C'est un socle modulaire
destiné à accueillir plusieurs outils indépendants, potentiellement dans des domaines différents.

Chaque outil doit :

- résoudre un problème concret et suffisamment pénible ;
- pouvoir être essayé en self-service ;
- apporter une valeur réelle dans sa version gratuite ;
- être distribuable sans dépendre d'une prospection commerciale lourde ;
- ne devenir un SaaS plus complet que si l'usage réel confirme un besoin récurrent.

Le rapprochement des relevés fournisseurs est le **premier pari produit** de mjtools. Il ne définit
pas à lui seul le positionnement futur de toute la plateforme.

Le Checker gratuit est la porte d'entrée et la preuve de compétence. Le produit commercial visé
n'est pas un simple comparateur ponctuel : c'est un workflow qui mémorise le contexte de chaque
client et fournisseur afin d'éviter de refaire le même travail à chaque période.

---

# 1. OBJECTIF DU PRODUIT

Le produit aide les équipes comptables à rapprocher un relevé envoyé par un fournisseur avec leurs propres données comptables.

Le principe fondamental est :

> L'utilisateur ne doit plus vérifier manuellement toutes les transactions. Le logiciel élimine les correspondances suffisamment certaines et concentre l'attention humaine sur les exceptions.

Exemple :

- 347 lignes analysées ;
- 318 rapprochées automatiquement ;
- 12 correspondances possibles ;
- 9 opérations absentes du ledger ;
- 3 écarts de montant ;
- 5 cas ambigus.

Résultat :

> **29 lignes nécessitent votre attention au lieu de 347.**

Le produit ne cherche pas à obtenir artificiellement 100 % de rapprochement.

Il doit privilégier la fiabilité :

> **Une absence de correspondance est préférable à une mauvaise correspondance présentée comme certaine.**

---

# 2. UTILISATEURS CIBLES

## Cible prioritaire

La conception et la monétisation initiales ciblent en priorité :

> **Les bookkeepers indépendants, petits cabinets comptables et petites équipes Accounts Payable
> qui traitent régulièrement environ 10 à 100 relevés fournisseurs par mois, sans vouloir une
> solution enterprise lourde ni un projet d'intégration.**

Ces utilisateurs sont prioritaires parce qu'ils répètent le même travail sur plusieurs fournisseurs
et plusieurs périodes. Cette répétition crée la valeur d'un produit payant qui mémorise les mappings,
les conventions et l'historique.

Utilisateurs concernés :

- bookkeeper indépendant ;
- cabinet comptable traitant plusieurs clients ;
- comptable fournisseurs ;
- collaborateur Accounts Payable ;
- responsable administratif/financier dans une petite structure.

## Entreprises pertinentes

Le produit devient particulièrement pertinent lorsque l'entreprise :

- possède plusieurs fournisseurs actifs ;
- reçoit régulièrement des relevés fournisseurs ;
- traite suffisamment de transactions pour que la vérification manuelle soit pénible ;
- utilise encore Excel ou des vérifications manuelles ;
- peut exporter son ledger/AP vers CSV ou Excel ;
- ne dispose pas déjà d'une solution satisfaisante de rapprochement automatique.

## Cibles non prioritaires

Ne pas optimiser initialement le produit pour :

- microentreprises avec très peu de factures fournisseurs ;
- grands groupes nécessitant des workflows ERP complexes ;
- entreprises demandant immédiatement des intégrations comptables profondes ;
- cas multi-devises extrêmement complexes ;
- rapprochements nécessitant des règles comptables spécifiques à une grande organisation.

---

# 3. TERMINOLOGIE FONCTIONNELLE

## Supplier Statement

Document envoyé par un fournisseur présentant sa vision du compte du client.

Il peut notamment contenir :

- numéro de facture ;
- date ;
- montant ;
- avoir ;
- paiement ;
- solde ;
- référence fournisseur.

## AP Ledger / Supplier Ledger

Données comptables internes représentant ce que l'entreprise pense devoir au fournisseur et les opérations enregistrées dans sa comptabilité.

## Reconciliation

Comparaison des deux sources afin de déterminer :

- ce qui correspond ;
- ce qui diffère ;
- ce qui manque ;
- ce qui est ambigu.

## Exception

Élément qui ne peut pas être éliminé automatiquement et nécessite potentiellement l'attention de l'utilisateur.

---

# 4. PÉRIMÈTRES PRODUIT

Les fonctionnalités sont volontairement séparées en trois périmètres. La vision finale ne constitue
pas une liste à terminer avant de lancer ou de vendre.

## Périmètre A — Free Checker à terminer et publier

Le Free Checker est un **outil de rapprochement manuel self-service**, utilisable sans compte. Il
doit démontrer la qualité du moteur sur des fichiers CSV/XLSX réels, y compris imparfaits.

Il permet :

1. d'importer un relevé fournisseur ;
2. d'importer un export comptable ;
3. d'identifier les colonnes importantes ;
4. de normaliser les données ;
5. de rechercher automatiquement les correspondances ;
6. de distinguer correspondances certaines, possibles et anomalies ;
7. d'expliquer chaque résultat ;
8. de permettre à l'utilisateur de vérifier les cas incertains ;
9. de produire une synthèse ;
10. d'exporter le résultat ;
11. d'identifier ou confirmer une devise unique par rapprochement et de bloquer les incohérences ;
12. de vérifier facultativement la cohérence d'un solde lorsque le fichier fournit les informations
    nécessaires ;
13. de mesurer, sans journaliser les données comptables, l'usage et les blocages du parcours.

Le Free Checker doit fonctionner sans connexion à Xero, QuickBooks ou un autre logiciel comptable.
Il ne doit pas être volontairement dégradé pour forcer une conversion.

## Périmètre B — Premier produit payant minimal

Le premier produit payant ne cherche pas encore à automatiser tout le processus. Il supprime la
répétition la plus évidente entre deux périodes.

Il comprend exactement :

1. un compte utilisateur et un espace de travail ;
2. une organisation simple `client → fournisseur` ;
3. la possibilité d'enregistrer, après un rapprochement réussi, le fournisseur et son mapping ;
4. la mémorisation de la devise, des colonnes et des conventions de lecture ;
5. la proposition du mapping précédent lors de la période suivante ;
6. la détection d'un changement de structure avant de réutiliser un mapping ;
7. un historique simple des rapprochements et exports ;
8. la possibilité de reprendre un rapprochement ;
9. la facturation, les quotas et la suppression des données ;
10. les garanties minimales de sécurité nécessaires à la conservation de données comptables.

Le scénario de vente à valider est :

> **J'ai configuré ce fournisseur le mois dernier. Ce mois-ci, mjtools reconnaît son format et je
> ne repars pas de zéro.**

## Périmètre C — Après validation commerciale

Ces capacités ne sont construites qu'après confirmation d'un usage récurrent ou de premiers
paiements :

1. traitement batch de plusieurs fournisseurs ;
2. continuité des exceptions entre périodes ;
3. historique et dossier d'audit enrichis ;
4. PDF texte avec validation de l'extraction ;
5. réception des relevés par email ;
6. OCR local pour les scans si la demande le justifie ;
7. premières intégrations comptables choisies selon les usages observés ;
8. fonctionnalités d'équipe et automatisations plus avancées.

Le périmètre C décrit une direction, pas un engagement de livraison immédiat.

Dans les sections techniques suivantes, toute mention historique de « V1 » désigne le périmètre A
(Free Checker), sauf indication contraire.

---

# 5. FORMATS ACCEPTÉS

## Free Checker

Formats obligatoires :

- CSV ;
- XLSX.

XLS peut éventuellement être accepté s'il n'ajoute pas une complexité disproportionnée.

## Après validation

Le PDF n'est **pas obligatoire avant la publication et la validation du Free Checker**.

Cela inclut :

- PDF texte ;
- PDF scanné ;
- photos ;
- OCR.

La raison fonctionnelle est simple : nous voulons d'abord valider la valeur du **moteur de rapprochement**, pas celle du moteur d'extraction documentaire.

---

# 6. PARCOURS UTILISATEUR PRINCIPAL

## Étape 1 — Arrivée

La page doit expliquer immédiatement la fonction :

> **Compare your supplier statement with your ledger and review only the differences.**

L'utilisateur doit comprendre qu'il peut essayer l'outil directement.

CTA principal :

> **Reconcile a statement**

Aucune création de compte obligatoire avant l'essai gratuit initial.

---

# 7. IMPORT DU SUPPLIER STATEMENT

L'utilisateur sélectionne ou glisse-dépose son fichier.

L'interface affiche :

- nom du fichier ;
- type ;
- nombre de lignes détectées ;
- aperçu des premières lignes.

Le produit tente d'identifier automatiquement :

- ligne contenant les en-têtes ;
- référence/document number ;
- date ;
- montant ;
- éventuellement type d'opération ;
- éventuellement débit/crédit.

L'utilisateur doit toujours pouvoir corriger la détection.

---

# 8. IMPORT DU LEDGER

Même principe.

L'utilisateur importe son export comptable.

Le système affiche :

- fichier ;
- nombre de lignes ;
- aperçu ;
- colonnes détectées.

Il tente d'identifier :

- numéro de facture/référence ;
- date ;
- montant ;
- type d'opération si disponible ;
- éventuellement fournisseur.

---

# 9. MAPPING DES COLONNES

Avant le rapprochement, une étape permet de confirmer :

### Supplier Statement

**Reference**
→ Invoice Number

**Date**
→ Transaction Date

**Amount**
→ Gross Amount

### Ledger

**Reference**
→ Document No.

**Date**
→ Posting Date

**Amount**
→ Amount

Chaque champ doit présenter un aperçu de quelques valeurs.

Exemple :

> Reference → `Invoice No.`  
> INV-004581  
> INV-004582  
> INV-004583

L'utilisateur peut modifier le mapping.

---

# 10. CHAMPS MINIMUMS

Pour la première version, le moteur doit principalement travailler avec :

- référence ;
- montant ;
- date.

La référence et le montant sont particulièrement importants.

Le système doit refuser de prétendre effectuer un rapprochement fiable lorsque les données disponibles sont insuffisantes.

Il doit alors expliquer le problème plutôt que produire artificiellement un résultat.

---

# 11. NORMALISATION DES RÉFÉRENCES

Le produit conserve TOUJOURS la valeur originale.

Une seconde représentation peut être utilisée pour la comparaison.

Exemples :

`INV-004583`

peut être normalisé pour permettre une comparaison avec :

`INV004583`

Certaines différences purement typographiques peuvent être ignorées :

- espaces superflus ;
- casse ;
- certains séparateurs ;
- ponctuation clairement non significative.

Cependant :

`INV-004583`

et

`4583`

ne doivent pas automatiquement devenir une correspondance certaine simplement parce qu'ils partagent quatre chiffres.

La suppression :

- des préfixes ;
- des zéros ;
- de parties d'une référence

doit diminuer la certitude du rapprochement et nécessiter d'autres preuves, notamment le montant.

---

# 12. NORMALISATION DES MONTANTS

Le système doit reconnaître comme équivalentes les représentations numériques compatibles.

Exemples :

`1 240,00`

`1240.00`

`1,240.00`

selon le format détecté.

Le système doit distinguer :

- montant positif ;
- montant négatif ;
- débit ;
- crédit.

Il ne doit jamais simplement supprimer le signe pour obtenir une correspondance.

---

# 13. NORMALISATION DES DATES

Le produit doit comprendre différents formats courants :

- 12/08/2026 ;
- 2026-08-12 ;
- 12-08-2026 ;
- formats locaux compatibles.

La valeur originale reste conservée.

Une différence de date n'empêche pas nécessairement une correspondance.

Exemple :

Statement :
12/08/2026

Ledger :
13/08/2026

peut constituer un candidat si les autres éléments concordent.

Mais cette tolérance ne transforme pas automatiquement le candidat en correspondance certaine.

---

# 14. PRINCIPE DU MOTEUR DE RAPPROCHEMENT

Le moteur fonctionne progressivement du cas le plus sûr vers le cas le moins sûr.

Les éléments déjà rapprochés de manière certaine sont retirés des recherches suivantes.

L'objectif est de réduire progressivement l'ensemble restant.

---

# 15. CORRESPONDANCE EXACTE

Cas idéal :

Statement :

`INV-4583 | 12/08/2026 | 1240.00`

Ledger :

`INV-4583 | 12/08/2026 | 1240.00`

Résultat :

> **Matched — Exact**

Explication :

> Same reference, amount and date.

Cette catégorie peut être automatiquement considérée comme rapprochée.

---

# 16. CORRESPONDANCE NORMALISÉE

Exemple :

Statement :

`INV-004583 | 1240.00`

Ledger :

`INV004583 | 1 240,00`

Après normalisation :

- référence compatible ;
- montant identique.

Résultat possible :

> **Matched — Normalized**

Explication :

> Same normalized reference and amount. Formatting differences ignored.

La transformation appliquée doit pouvoir être consultée.

---

# 17. CORRESPONDANCE AVEC VARIATION DE DATE

Exemple :

Statement :

`INV-4583 | 12/08 | 1240`

Ledger :

`INV-4583 | 13/08 | 1240`

Le moteur constate :

- référence identique ;
- montant identique ;
- date proche.

Selon la combinaison de preuves, le résultat peut rester suffisamment fort pour être automatiquement rapproché ou être envoyé en vérification.

Le seuil exact devra être calibré avec les données de test.

---

# 18. CORRESPONDANCE PROBABLE

Exemple :

Statement :

`INV-004583 | 12/08 | 1240`

Ledger :

`4583 | 13/08 | 1240`

Le moteur constate :

- montant identique ;
- date très proche ;
- référence partiellement compatible.

Mais la référence a subi une transformation importante.

Résultat :

> **Possible Match**

Explication :

> Same amount  
> Date difference: 1 day  
> Similar reference: INV-004583 ↔ 4583

L'utilisateur décide :

**Confirm match**

ou

**Reject match**

---

# 19. AMBIGUÏTÉ

Si une ligne du statement possède plusieurs candidats raisonnables, le moteur ne doit pas en sélectionner arbitrairement un.

Exemple :

Statement :

`INV-4583 | 500 €`

Ledger :

`4583 | 500 €`

et

`INV4583 | 500 €`

Résultat :

> **Ambiguous — Review required**

L'utilisateur voit les candidats et choisit éventuellement la bonne correspondance.

---

# 20. ONE-TO-MANY / MANY-TO-ONE

Les rapprochements réels ne sont pas nécessairement toujours 1↔1.

Le produit doit donc prévoir fonctionnellement :

### One-to-many

Une ligne du statement peut correspondre à plusieurs lignes du ledger.

### Many-to-one

Plusieurs lignes du statement peuvent correspondre à une ligne du ledger.

Cependant, en V1 :

- les combinaisons simples peuvent être proposées ;
- elles doivent être explicitement identifiées ;
- elles ne doivent pas être automatiquement validées si la relation est ambiguë.

Les scénarios combinatoires complexes restent hors du périmètre initial.

---

# 21. STATUTS FONCTIONNELS

Chaque élément doit aboutir à un état compréhensible.

## Matched

Correspondance suffisamment certaine.

Sous-types possibles :

- Exact Match ;
- Normalized Match.

## Possible Match

Le système possède un candidat crédible mais demande confirmation.

## Review Required

Le système ne dispose pas de suffisamment d'éléments pour prendre une décision.

## Missing in Ledger

L'opération apparaît sur le relevé fournisseur mais aucune opération correspondante n'est trouvée dans la comptabilité.

## Ledger Only

L'opération apparaît dans la comptabilité mais pas sur le relevé.

## Amount Mismatch

Une relation forte existe entre les opérations, mais les montants diffèrent.

## Duplicate Suspected

Plusieurs lignes semblent représenter la même opération.

---

# 22. CRÉDITS / AVOIRS

Le système doit reconnaître qu'une ligne négative ou identifiée comme crédit ne doit pas être traitée comme une facture classique.

Un avoir présent chez le fournisseur mais absent du ledger doit apparaître clairement.

Exemple :

> **Credit missing in ledger**  
> CN-00824  
> -420 €

Cette anomalie peut représenter de l'argent que l'entreprise risque de ne pas correctement prendre en compte.

---

# 23. ÉCART DE MONTANT

Lorsqu'une référence correspond fortement mais que le montant diffère :

Statement :

> INV-1248 — 1 200 €

Ledger :

> INV-1248 — 1 150 €

Résultat :

> **Amount mismatch**

Affichage :

> Statement: €1,200  
> Ledger: €1,150  
> Difference: **€50**

Le système ne doit pas automatiquement choisir lequel des deux montants est correct.

Il signale uniquement l'écart.

---

# 24. DOUBLONS

Le système doit détecter les situations suspectes comme :

Statement :

`INV-123 | 500 €`

Ledger :

`INV-123 | 500 €`  
`INV-123 | 500 €`

Résultat :

> **Potential duplicate in ledger**

Il ne doit pas automatiquement supprimer ou corriger quoi que ce soit.

---

# 25. ÉLÉMENTS ABSENTS

Deux directions doivent être distinguées.

## Statement → Ledger

Présent chez le fournisseur, absent des données internes :

> **Missing in Ledger**

Cela peut notamment indiquer une facture ou un avoir non enregistré.

## Ledger → Statement

Présent en interne, absent du relevé :

> **Ledger Only**

Cela peut être une erreur, mais également un problème de timing.

Le produit ne doit donc pas affirmer automatiquement :

> « erreur comptable ».

Il indique le fait observable.

---

# 26. TIMING DIFFERENCE

Le produit doit être prudent avec les opérations proches de la période de clôture.

Une transaction enregistrée dans un système mais pas encore dans l'autre peut simplement correspondre à un décalage temporel.

Le produit peut signaler :

> **Possible timing difference**

mais ne doit pas automatiquement considérer l'élément comme une erreur.

---

# 27. RÉSULTAT GLOBAL

Le résultat principal doit être extrêmement simple.

Exemple :

# Reconciliation complete

**347 transactions analyzed**

### 318

Matched automatically

### 12

Possible matches

### 9

Missing in ledger

### 3

Amount mismatches

### 5

Need review

Puis l'information principale :

> **29 items need your attention instead of 347.**

C'est le principal moment de valeur du produit.

---

# 28. INDICATEUR DE VALEUR

Le produit doit calculer :

**Total analysé**

et

**Nombre restant à examiner**

afin de pouvoir afficher :

> **91.6% of transactions cleared automatically.**

Ce pourcentage décrit la réduction de la revue humaine.

Il ne doit PAS être présenté comme :

> « 91,6 % de précision »

car ce serait une affirmation différente.

---

# 29. MONTANTS CONCERNÉS

Lorsque pertinent, la synthèse doit également présenter :

> **€4,820 across exceptions**

avec distinction possible :

- missing in ledger ;
- amount differences ;
- credits ;
- autres éléments financiers.

Il faut éviter d'additionner des catégories incompatibles de manière trompeuse.

---

# 30. ÉCRAN DES EXCEPTIONS

Après la synthèse :

> **Review 29 items**

L'utilisateur arrive sur la liste des éléments nécessitant son attention.

Filtres :

- All ;
- Possible Matches ;
- Missing in Ledger ;
- Ledger Only ;
- Amount Mismatch ;
- Duplicates ;
- Review Required.

Les transactions automatiquement rapprochées sont masquées par défaut mais restent consultables.

---

# 31. COMPARAISON VISUELLE

Lorsqu'une relation existe, afficher les deux côtés.

### Supplier Statement

INV-004583  
12 Aug 2026  
€1,240

### Ledger

4583  
13 Aug 2026  
€1,240

### Why we linked these

✓ Same amount  
✓ Similar normalized reference  
~ Date difference: 1 day

### Result

**Possible Match**

Actions :

**Confirm Match**

**Reject Match**

---

# 32. EXPLICABILITÉ

Toute décision automatique doit avoir une justification humaine.

L'utilisateur doit pouvoir savoir :

- quelles valeurs originales ont été comparées ;
- quelles valeurs normalisées ont été utilisées ;
- quels critères concordent ;
- quels critères diffèrent ;
- pourquoi le statut a été attribué.

Exemple :

> **Normalized Match**
>
> Reference:
> `INV-0004583` → `4583`
>
> Ledger:
> `INV4583` → `4583`
>
> Amount:
> €1,240 = €1,240
>
> Date:
> 12 Aug vs 12 Aug

Cette transparence constitue une partie importante du produit.

---

# 33. SCORE INTERNE

Le moteur peut utiliser un score interne pour ordonner les candidats.

Cependant, le produit ne doit PAS afficher :

> **94 % probability**

tant que ce nombre n'est pas réellement calibré statistiquement.

Préférer des catégories :

- Certain ;
- Strong candidate ;
- Ambiguous ;
- No match.

Le score interne sert au moteur, pas à créer une fausse impression scientifique.

---

# 34. ACTIONS HUMAINES

Pour les éléments nécessitant une décision :

### Confirm Match

L'utilisateur confirme que les éléments correspondent.

### Reject Match

Le candidat proposé est incorrect.

### Manual Match

L'utilisateur peut sélectionner manuellement une autre transaction.

### Leave for Review

L'utilisateur ne sait pas encore.

La V1 ne modifie jamais directement la comptabilité source.

---

# 35. TRAÇABILITÉ DU RÉSULTAT

Le rapport doit distinguer :

- décisions automatiques ;
- décisions confirmées manuellement ;
- rapprochements manuels ;
- éléments non résolus.

Une confirmation humaine ne doit jamais être présentée comme une décision automatique du moteur.

---

# 36. EXPORT

À la fin du rapprochement, l'utilisateur peut exporter le résultat.

L'export doit permettre de retrouver :

- transaction statement ;
- transaction ledger associée ;
- statut ;
- différence éventuelle ;
- raison du rapprochement ;
- décision humaine éventuelle.

L'objectif est que le résultat reste exploitable hors du produit.

---

# 37. ERREURS D'IMPORT

Le produit doit détecter et expliquer clairement :

- fichier vide ;
- fichier corrompu ;
- aucun tableau exploitable ;
- absence de colonne montant ;
- absence de référence exploitable ;
- format numérique incohérent ;
- mapping incomplet ;
- fichier excessivement volumineux ;
- lignes impossibles à interpréter.

Il ne doit jamais lancer silencieusement un rapprochement sur des données manifestement incorrectes.

---

# 38. CONTRÔLE AVANT ANALYSE

Avant de lancer le rapprochement, afficher une courte synthèse :

> Supplier Statement  
> 347 rows
>
> Ledger  
> 391 rows
>
> Reference ✓  
> Date ✓  
> Amount ✓
>
> Ready to reconcile

Si un problème important existe :

> **Review required before reconciliation**

avec explication.

---

# 39. DONNÉES ORIGINALES

Le produit ne doit jamais modifier silencieusement les données importées.

La normalisation sert uniquement à comparer.

Toujours distinguer :

**Original**

et

**Normalized for matching**

Cette règle est fondamentale pour la confiance.

---

# 40. CONFIDENTIALITÉ DU FREE CHECKER

Le produit manipule des données financières.

Le parcours gratuit doit donc expliquer clairement :

- pourquoi les fichiers sont utilisés ;
- s'ils sont temporairement conservés ;
- quand ils sont supprimés ;
- qu'ils ne sont pas vendus ou rendus publics ;
- si les résultats persistent ou disparaissent après la session.

La promesse doit être simple et visible avant l'upload.

---

# 41. POSITIONNEMENT DU FREE CHECKER

Le Checker gratuit n'est pas une démonstration artificielle.

Il doit réellement effectuer le rapprochement.

Promesse :

> **Upload your supplier statement and ledger. See what matches and what needs attention.**

L'utilisateur doit pouvoir constater la qualité du moteur avant de payer.

---

# 42. FRONTIÈRE GRATUIT / PAYANT

## Gratuit = effectuer le travail maintenant

Le gratuit comprend principalement :

- import manuel ;
- CSV/XLSX ;
- mapping ;
- rapprochement ;
- résultats ;
- revue des exceptions ;
- export simple ;
- devise unique comme garde-fou ;
- contrôle de cohérence du solde lorsqu'il est possible.

Il ne faut pas volontairement rendre le rapprochement gratuit mauvais ou incomplet.

## Premier payant = se souvenir du travail précédent

Le premier payant comprend uniquement le périmètre B défini au §4 : compte, clients et
fournisseurs, mappings sauvegardés, détection des changements de format, historique simple,
reprise et facturation.

Il ne nécessite ni PDF, ni batch, ni intégration comptable pour être proposé et testé.

## Après validation = automatiser davantage

Le batch, la continuité des exceptions, le PDF, l'email et les intégrations appartiennent au
périmètre C. Ils sont priorisés selon les demandes et comportements observés, pas parce qu'ils
semblent intéressants isolément.

La logique commerciale est :

> Gratuit : **« Fais-le pour moi maintenant. »**

> Payant : **« Fais-le désormais à ma place. »**

---

# 43. CONVERSION VERS LE PAYANT

Après un rapprochement réussi, le CTA ne doit pas interrompre le travail.

Une fois la valeur démontrée :

> **You reconciled 347 transactions and only reviewed 29.**

Puis :

> **Do this every month?**
>
> Save this supplier and its mapping so you don't have to configure it again.

C'est le moment naturel pour proposer la création d'un compte.

Pas avant la preuve de valeur.

Avant de construire tout le périmètre payant, ce CTA doit permettre de mesurer une intention réelle :

- clic pour sauvegarder le fournisseur ;
- adresse email ou création de compte ;
- nombre de fournisseurs traités par mois ;
- logiciel comptable utilisé ;
- intérêt pour un prix présenté clairement.

---

# 44. DIFFÉRENCIATION FONCTIONNELLE RECHERCHÉE

Le produit ne cherchera pas à battre les plateformes enterprise sur le nombre d'intégrations.

Son identité fonctionnelle repose d'abord sur trois principes :

> **Accepter les données imparfaites. Ne jamais masquer l'incertitude. Expliquer chaque résultat.**

Il doit chercher à être excellent sur quatre choses :

### 1. Import extrêmement simple

Deux fichiers et très peu de configuration.

### 2. Matching tolérant aux données imparfaites

Ne pas exiger que toutes les valeurs soient strictement identiques.

### 3. Prudence

Ne jamais forcer une correspondance lorsque plusieurs interprétations sont plausibles.

### 4. Explicabilité

Montrer précisément pourquoi quelque chose correspond ou ne correspond pas.

---

# 45. CE QUE LE PRODUIT NE DOIT PAS FAIRE

La V1 ne doit pas :

- modifier la comptabilité ;
- créer automatiquement une facture ;
- payer une facture ;
- envoyer des messages aux fournisseurs ;
- décider quelle entreprise a raison lors d'un écart ;
- masquer une anomalie incertaine ;
- utiliser une IA générative pour décider arbitrairement des correspondances ;
- dépendre d'une API d'IA payante pour son fonctionnement principal ;
- prétendre qu'un score interne représente une probabilité comptable réelle.

Le produit détecte et organise.

L'humain reste responsable des cas nécessitant un jugement.

La « mémoire » du produit payant désigne des mappings, règles et décisions structurés. Elle ne
suppose ni machine learning ni appel à une IA externe. Des bibliothèques locales ou open source
pourront être utilisées plus tard pour l'extraction documentaire, sans modifier ce principe.

---

# 46. HORS FREE CHECKER INITIAL

Sont explicitement hors du périmètre A :

- Xero ;
- QuickBooks ;
- Sage ;
- ERP ;
- API comptables ;
- boîte email dédiée ;
- récupération automatique des statements ;
- PDF complexe ;
- OCR ;
- scans/photos ;
- relances fournisseurs ;
- écritures comptables automatiques ;
- résolution automatique des litiges ;
- workflow d'approbation complexe ;
- multi-entités avancé ;
- gestion complète Accounts Payable ;
- application mobile ;
- IA conversationnelle ;
- reporting financier général ;
- automatisation des paiements.

Les comptes, fournisseurs sauvegardés et mappings mémorisés appartiennent au premier payant (§4,
périmètre B). Le batch, le PDF, l'email et les intégrations appartiennent au périmètre C.

Ces fonctionnalités ne doivent pas entrer dans le Free Checker simplement parce qu'elles semblent
intéressantes, et le périmètre C ne doit pas être traité comme un prérequis à la première vente.

---

# 47. CAS DE TEST FONCTIONNELS OBLIGATOIRES

Le moteur devra au minimum être évalué sur :

### Exact

Même référence, montant et date.

### Format de référence

`INV-123` ↔ `INV123`

### Casse

`inv-123` ↔ `INV-123`

### Espaces

`INV 123` ↔ `INV123`

### Zéros

`INV-000123` ↔ `INV-123`

### Préfixe différent

`INV-00123` ↔ `123`

### Montant formaté différemment

`1 240,00` ↔ `1240.00`

### Date différente

12/08 ↔ 13/08.

### Référence identique / montant différent

Doit devenir Amount Mismatch, pas Matched.

### Montant identique / référence différente

Ne doit pas suffire seul à produire automatiquement un match.

### Plusieurs candidats

Doit devenir Ambiguous.

### Facture absente du ledger

Missing in Ledger.

### Ligne uniquement ledger

Ledger Only.

### Crédit

Signe et nature correctement interprétés.

### Doublon

Duplicate Suspected.

### One-to-many simple

Détecté/proposé sans validation dangereuse.

### Données insuffisantes

Review Required ou erreur explicite.

---

# 48. CRITÈRE FONCTIONNEL PRINCIPAL DU MOTEUR

La question fondamentale n'est pas :

> **Combien de lignes avons-nous réussi à matcher ?**

mais :

> **Combien de lignes pouvons-nous retirer de la revue humaine sans introduire de correspondances incorrectes ?**

Un moteur qui automatise prudemment 70 % des lignes peut être meilleur qu'un moteur qui prétend en automatiser 95 % mais crée des faux rapprochements.

---

# 49. MESURES À OBSERVER

## Qualité fonctionnelle

Observer :

- lignes automatiquement rapprochées ;
- faux auto-matchs identifiés ;
- lignes laissées en revue ;
- qualité des candidats proposés ;
- types d'exceptions rencontrés ;
- capacité à expliquer les décisions.

## Usage du Free Checker

Observer :

- personnes commençant un import ;
- imports terminés ;
- rapprochements terminés ;
- erreurs d'import ;
- utilisateurs consultant les exceptions ;
- exports ;
- utilisations répétées ;
- clics sur « sauvegarder ce fournisseur » ;
- créations de compte après un résultat ;
- demandes de sauvegarde/historique/automatisation ;
- nombre déclaré de fournisseurs traités par mois ;
- intérêt pour le prix présenté ;
- demandes de PDF, batch ou intégration, mesurées séparément.

Ces données serviront à décider du SaaS payant.

---

# 50. CRITÈRES DE VALIDATION DU PRODUIT

Le Free Checker donne un signal positif si nous observons simultanément :

### Valeur

Le moteur élimine réellement une part importante du travail manuel sur des données réalistes.

### Confiance

Les auto-matchs restent suffisamment fiables pour que l'utilisateur n'ait pas besoin de tout revérifier.

### Utilisation

De vrais utilisateurs terminent des rapprochements avec leurs propres fichiers.

### Récurrence

Certains utilisateurs reviennent effectuer d'autres rapprochements.

### Besoin d'automatisation

Certains utilisateurs montrent naturellement qu'ils veulent :

- sauvegarder leurs mappings ;
- traiter plusieurs fournisseurs ;
- conserver l'historique ;
- ne plus importer manuellement ;
- automatiser le processus.

C'est ce dernier point qui valide particulièrement le futur SaaS.

## Validation du premier payant

Le périmètre B est validé lorsque des utilisateurs ayant terminé un vrai rapprochement acceptent de
créer un compte et montrent une intention crédible de payer pour retrouver le fournisseur, son
mapping et son historique à la période suivante.

Le batch, le PDF ou une intégration ne doivent pas être utilisés pour masquer l'absence d'intérêt
pour cette mémoire récurrente fondamentale.

---

# 51. SIGNAUX D'ARRÊT OU DE REMISE EN QUESTION

Le produit devra être sérieusement réévalué si les données réelles montrent durablement que :

- les fichiers sont tellement hétérogènes que CSV/XLSX n'apporte pratiquement aucune valeur ;
- les faux rapprochements restent difficiles à éviter ;
- la majorité des transactions doivent toujours être vérifiées ;
- les utilisateurs ne font pas confiance au résultat ;
- le problème est facilement résolu avec leur Excel existant ;
- les utilisateurs utilisent le Checker une fois mais n'ont aucun besoin récurrent ;
- personne ne manifeste d'intérêt pour les fonctions d'automatisation ;
- l'acquisition self-service ne génère aucun usage malgré une exposition suffisante.

Une simple difficulté technique ou un utilisateur insatisfait ne suffit pas à abandonner.

---

# 52. PROGRESSION DES TROIS PÉRIMÈTRES

La progression retenue est :

### A — Free Checker

Deux fichiers, un rapprochement complet, des exceptions expliquées et un export.

↓ validation de la valeur, de la confiance et de la récurrence

### B — Premier payant

Compte, client, fournisseur, mapping mémorisé, détection de dérive, historique simple et reprise.

↓ premiers paiements et demandes observées

### C — Automatisation progressive

Batch, continuité des exceptions, PDF, email, OCR local et intégrations ciblées.

Le produit passe ainsi de :

> **outil de rapprochement ponctuel**

à :

> **système de rapprochement récurrent**

puis éventuellement à :

> **automatisation du rapprochement fournisseur.**

Les évolutions naturelles sont :

**Fournisseurs sauvegardés**

↓

**Mappings/règles mémorisés**

↓

**Historique**

↓

**Traitement batch**

↓

**Réception automatique**

↓

**Ledger connecté**

↓

**Rapprochement automatique**

↓

**Exceptions uniquement**

---

# 53. VISION FONCTIONNELLE FINALE

À terme, l'utilisateur ne devrait plus penser :

> « Je dois faire mes rapprochements fournisseurs. »

Le produit travaille en arrière-plan.

Exemple :

> **BuildCo Supplies — September reconciliation complete**
>
> 486 transactions analyzed  
> 472 cleared automatically
>
> **14 require attention**
>
> €3,420 involved
>
> Review exceptions

L'utilisateur ouvre uniquement les 14 éléments.

C'est la destination fonctionnelle du produit.

Cette destination ne doit pas être confondue avec le périmètre nécessaire à la première vente.

---

# 54. PRINCIPE DIRECTEUR DU PRODUIT

Toute nouvelle fonctionnalité doit être évaluée avec cette question :

> **Réduit-elle réellement le travail nécessaire pour trouver et traiter les exceptions ?**

Si la réponse est non, elle n'est probablement pas prioritaire.

Le produit n'est pas conçu pour remplacer la comptabilité.

Il est conçu pour éliminer le travail répétitif situé **entre le relevé fournisseur et la détection des anomalies comptables**.

Une seconde question s'applique avant validation commerciale :

> **Cette fonctionnalité est-elle nécessaire pour prouver la valeur ou obtenir le prochain signal
> commercial, ou appartient-elle à une phase ultérieure ?**

---

# 55. RÉSUMÉ DU PÉRIMÈTRE A — FREE CHECKER

Nous construisons exactement :

**Supplier Statement CSV/XLSX**

-

**AP Ledger CSV/XLSX**

↓

**Détection/mapping des colonnes**

↓

**Normalisation contrôlée**

↓

**Matching déterministe progressif**

↓

**Matched / Possible / Missing / Mismatch / Duplicate / Review**

↓

**Explication de chaque décision**

↓

**Vue synthétique centrée sur les exceptions**

↓

**Validation humaine des cas incertains**

↓

**Export du résultat**

Le produit doit répondre à une seule promesse :

> **Donnez-nous les deux côtés. Nous éliminons ce qui concorde et vous montrons uniquement ce qui mérite votre attention.**

---

# 56. RÉSUMÉ DU PÉRIMÈTRE B — PREMIER PAYANT

Nous construisons après validation du Checker :

**Rapprochement gratuit réussi**

↓

**Création de compte au moment de sauvegarder**

↓

**Client + fournisseur + mapping mémorisé**

↓

**Nouvelle période et nouveaux fichiers**

↓

**Réutilisation contrôlée du mapping + détection de changement de structure**

↓

**Nouveau rapprochement + historique + export**

Le premier payant doit répondre à une seule promesse :

> **Configurez ce fournisseur une fois. À la prochaine période, ne repartez pas de zéro.**

Le batch, le PDF, l'email, l'OCR et les intégrations ne sont pas requis pour tester cette promesse.
