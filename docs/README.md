# Documentation mjtools

Ce dossier est le point d'entrée documentaire humain du repository.

## Ordre de lecture

1. [`FUNCTIONAL_SPEC.md`](FUNCTIONAL_SPEC.md) — intention fonctionnelle et trois périmètres ;
2. [`IMPLEMENTATION_STATUS.md`](IMPLEMENTATION_STATUS.md) — état réel du développement ;
3. [`SUPPLIER_RECONCILIATION_PLAN.md`](SUPPLIER_RECONCILIATION_PLAN.md) — architecture et ordre de livraison.

Pour une IA ou un agent de développement, commencer par [`../AGENTS.md`](../AGENTS.md).

## Périmètres

- **A — Free Checker :** rapprochement complet sans compte, à stabiliser et publier ;
- **B — Premier payant :** clients, fournisseurs, mappings mémorisés, historique et reprise ;
- **C — Après validation :** batch, continuité des exceptions, PDF, email, OCR et intégrations.

Le périmètre C ne doit jamais être interprété comme une condition à la publication de A ou à la
première vente de B.

## Mise à jour

- une décision fonctionnelle modifie `FUNCTIONAL_SPEC.md` ;
- une décision d'architecture ou de séquencement modifie `SUPPLIER_RECONCILIATION_PLAN.md` ;
- tout progrès matériel modifie `IMPLEMENTATION_STATUS.md` ;
- les contradictions doivent être corrigées explicitement, jamais contournées silencieusement.
