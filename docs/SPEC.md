# Star Citizen Hauling Manager — Spécification

Stack : PHP 8.4, Symfony 7.4, Symfony UX Live Component + Twig Component, AssetMapper/Stimulus, Doctrine (Postgres).
Tout s'exécute via Docker : `docker compose run --rm --no-deps php <cmd>` (php, composer, bin/console, phpunit).

## Problèmes à résoudre (vs sc-hauling.tools)
1. **Aller-retours inutiles** : produire un tour de pickup/drop logique qui minimise les trajets (pas de
   retour sur un lieu déjà visité si évitable).
2. **Vue par mission, pas par ressource** : une caisse appartient à UNE mission. À chaque arrêt on dit
   « récupère/livre ces caisses de la mission X », jamais « 40 SCU de Laranite » en vrac.
3. **Visuel cargo** : afficher la grille cargo du vaisseau (façon sc-cargo.space), placer chaque caisse
   dans la grille, et mettre en surbrillance les caisses concernées par l'arrêt courant.

## Domaine (namespace `App\Domain`, PHP pur, sans Doctrine, 100 % testable)
- `Location` (id, name, system, planet, `LocationType` type, ?body) + `LocationGraph::tier(a, b): TravelTier`.
  `LocationType` = Surface | Orbital | Moon | Lagrange ; `body` = nom de la lune / du point de Lagrange.
  `TravelTier` (enum) avec `cost(): int` : Local 0 (même planète : surface + orbite, ou même lune / même Lagrange) ;
  Planetary 1 (planète <-> lunes / Lagrange, lune <-> lune) ; Stellar 3 (autre planète) ; Interstellar 10 (autre système).
  `LocationGraph::cost(a, b): int`.
- `Mission` (id, label, reward, `list<CargoLot>`, `MissionType` type = Direct, int maxContainerSize = 32) ; `validate()` lève
  `InvalidArgumentException` (message FR) si les lots sont incohérents avec le type. `MissionType` = Direct (1 pickup, 1 drop) |
  MultipleDropOff (1 pickup, N drops) | MultiplePickUp (N pickups, 1 drop) | FreeShopping (achats, destination commune optionnelle).
  `maxContainerSize` (1..32, hors plage = exception) est arrondi à la taille valide inférieure (20 -> 16) et limite le découpage
  (avec la plus grande caisse acceptée par le vaisseau). Les caisses explicites au-dessus de la limite sont refusées.
- `CargoLot` (id, missionId, resource, scu, pickup: Location, ?dropoff: Location (null = jamais déposé, reste à bord jusqu'au dernier arrêt ; `PlacedCrate::kept`), `list<Crate>` après découpe).
- `Crate` (id, lotId, missionId, sizeScu ∈ {1,2,4,8,16,24,32}), découpe gloutonne d'un lot en caisses.
- `Ship` (name, `CargoGrid`, slug, manufacturer ; `label()`) ; `CargoGrid` = `list<CargoBlock>` (x,y,z,width,length,height,?maxSize ; cellules de 1 SCU, coordonnées absolues,
  écarts entre blocs = cellules inexistantes) + boîte englobante width/length/height + `door`. Catalogue réel : `ShipCatalog` (`src/Domain/Data/ships.json`, 106 vaisseaux, `DEFAULT = drake-caterpillar`).
  Une caisse doit tenir dans UN bloc et sa taille <= maxSize du bloc.
- `Stop` (location, `list<Crate> pickups`, `list<Crate> drops`, `?TravelTier tierToNext`) ; `Route` = `list<Stop>` + `cost` + `?TravelTier startTier`.
- `RouteOptimizer::optimize(list<Mission>, Ship, Location $start): Route`
  Contraintes : pickup avant drop pour chaque caisse ; charge ≤ capacité du vaisseau à tout instant ;
  coût = somme des `TravelTier::cost()` (local gratuit, ordre local libre) ; heuristique (plus proche voisin + amélioration 2-opt/insertion) ; les arrêts
  sur un même lieu sont fusionnés. Doit éviter A→B→A→B quand A→A→B→B est possible.
- `CargoPlacer::place(Route, Ship): Placement` — assigne à chaque caisse une position (x,y,z) dans la grille.
  Règle : les caisses livrées en premier sont les plus proches de la porte (déchargement LIFO), et à un arrêt
  de pickup les caisses d'une même mission restent groupées. `Placement` expose, pour un arrêt N, l'état de la
  grille (quelles caisses sont à bord) + les caisses `toPickup` / `toDrop` à cet arrêt.

## UI (Live Components)
- `HaulingPlanner` (LiveComponent) : saisie des missions (ajout/suppression de lots), choix vaisseau + lieu de départ,
  bouton « Calculer le tour ». État dans le `LiveProp`.
- `RouteStepper` (LiveComponent) : navigation arrêt par arrêt (précédent/suivant), liste des actions groupées par MISSION.
- `CargoGrid` (TwigComponent/Live) : rendu CSS-grid de la soute, couches (z) sélectionnables, caisses colorées par
  mission, **surbrillance** (pulse) des caisses à pickup (vert) / drop (orange) de l'arrêt courant, les autres atténuées.
- Persistance Doctrine des plans (optionnel, en dernier) ; sinon session.

## Répartition des fichiers (pas de chevauchement entre agents)
- Agent « domain » : `src/Domain/**`, `tests/Domain/**`.
- Agent « ui » : `src/Twig/Components/**`, `src/Controller/**`, `templates/**`, `assets/**`, `tests/Ui/**`.
  L'agent UI consomme l'API du domaine ci-dessus ; tant qu'elle n'existe pas, il code contre les signatures de ce document.

## Qualité
KISS, classes `final readonly` quand possible, types stricts, pas de commentaire superflu. Tests PHPUnit sur le domaine
(cas aller-retour, capacité, ordre pickup/drop, placement LIFO).
