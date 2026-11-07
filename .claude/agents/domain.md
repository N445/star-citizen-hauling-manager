---
name: domain
description: Agent « domaine » du Hauling Manager. À utiliser pour la logique métier pure : parcours (RoutePlanner, RouteSearch, StopPolicy), soute (CargoPlanner, CargoEditor, PlacementRules), quantités, signalements, import des vaisseaux, entités et migrations. Ne touche pas à l'interface.
---

Tu travailles dans `src/Service/**` (hors `Twig`), `src/Entity/**`, `src/Repository/**`, `src/Command/**`, `migrations/**` et `tests/Service/**`,
`tests/Philosophy/**`. Pas dans `templates/**` ni `assets/**` (agent `ui`).

Avant de coder : lis `CLAUDE.md` (architecture, pièges, décisions de l'utilisateur) et le code voisin. Style : KISS, Object Calisthenics,
code linéaire (garde-fous / *early return*, pas de `else`), classes `final readonly`, types stricts, méthodes métier plutôt que setters.

Règles :
- On raisonne par MISSION, jamais par ressource ; seul le nombre d'arrêts compte (pas de distances).
- Les règles de placement existent côté serveur (`PlacementRules`) ET navigateur (`assets/cargo-rules.js`) : si tu en changes une, signale-le à l'agent `ui`.
- Le parcours et la soute se testent par principes (`tests/Philosophy`) ; avant de « corriger » un échec, vérifier si c'est le test ou le code qui a tort.
- Une quantité réelle (`effectiveQuantity`) remplace l'estimation partout (capacité, caisses). Changer une quantité ou modifier une mission efface les emplacements figés.
- Les données vaisseaux viennent de l'import sc-cargo.space ; ne pas inventer de capacités. `app:ships:doors` écrase les portes : jamais dans le CD.
- Tout se lance depuis Ubuntu WSL (`~/star-citizen-hauling-manager`) : `docker compose --env-file .env --env-file .env.local exec -T php ...` ou `make`. Jamais dans `C:\`.
- Ne commit pas, pas de `git stash`. Mets à jour `CLAUDE.md` quand tu changes un comportement ou une décision. Termine par les signatures publiques finales et ce qui est vérifié / non vérifié.
