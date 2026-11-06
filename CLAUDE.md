# Star Citizen Hauling Manager

Outil de planification de missions cargo (Star Citizen), inspiré de sc-hauling.tools et sc-cargo.space.
Repo : `git@github.com:N445/star-citizen-hauling-manager.git` (remote `origin`, branche `main`).

## Le but (ce qui distingue l'outil)
1. **Pas d'aller-retours inutiles** : un tour de pickup/drop logique, qui regroupe les arrêts par planète.
2. **Raisonner par MISSION, jamais par ressource** : une caisse appartient à UNE mission. À chaque arrêt on dit
   « récupère/livre ces caisses de la mission X », pas « 40 SCU de Laranite ».
3. **Soute visuelle** : la grille cargo réelle du vaisseau, caisses placées, caisses de l'arrêt courant en surbrillance
   (vert = pickup, orange = drop). L'utilisateur peut déplacer les caisses à la main (glisser-déposer).

## Stack
PHP 8.4, Symfony 7.4 LTS, Symfony UX Live Component + Twig Component, AssetMapper + Stimulus, Tom Select, Doctrine/Postgres
(installé, pas encore utilisé), PHPUnit. L'interface est en français.

## Lancer / tester (tout passe par Docker, PHP n'est pas installé sur l'hôte)
    docker compose up -d php                                   # http://localhost:8000
    docker compose exec -T php vendor/bin/phpunit              # toute la suite
    docker compose exec -T php vendor/bin/phpunit tests/Domain
    docker compose exec -T php php bin/console lint:twig templates
    docker compose run --rm --no-deps php composer install     # après un pull

- `vendor/` et `var/` sont dans des **volumes Docker natifs** (le partage Windows 9p rendait chaque requête ~3 s ; ~0,35 s
  maintenant). Conséquences : ils ne sont pas visibles depuis Windows, et il ne faut **pas** mettre de scripts ou fichiers
  temporaires dans `var/` depuis l'hôte.
- Le serveur est `php -S` avec `PHP_CLI_SERVER_WORKERS=4` (voir `docker/Dockerfile`, `docker/php.ini`, `compose.yaml`).

## Architecture
- `src/Domain/**` : PHP pur, sans Doctrine, testé (`tests/Domain`). Spéc détaillée : `docs/SPEC.md` (à lire en premier).
  - `Location`, `LocationType`, `TravelTier` (Local 0 / Planétaire 1 / Stellaire 3 / Interstellaire 10), `LocationGraph::tier()`.
    Il n'y a **pas de distances** : seul compte le type de trajet. Même planète = gratuit et ordre libre.
  - `Mission` (+ `MissionType` : direct, multipleDropOff, multiplePickUp, freeShopping ; `maxContainerSize` 1..32),
    `CargoLot` (`dropoff` nullable = achat libre sans destination, la caisse reste à bord), `Crate` (tailles 1,2,4,8,16,24,32).
  - `Ship`, `CargoGrid` (multi-blocs `CargoBlock` avec `maxSize`), `ShipCatalog` (charge `src/Domain/Data/ships.json`).
  - `RouteOptimizer` → `Route`/`Stop` ; `CargoPlacer` → `Placement`/`PlacedCrate` ; `Pin` = caisse déplacée à la main,
    conservée au recalcul ; `Placement::canPlace/move/validPositions`.
- `src/Twig/Components/**` : Live Components `HaulingPlanner`, `RouteStepper`, `CargoGrid` ; `Plan/PlanFactory` fait le pont UI → domaine.
  `src/Twig/ResourceCatalog.php` : ressources suggérées (saisie libre autorisée).
- `templates/`, `assets/` (Stimulus : `tom-select_controller.js`, etc.), `tests/Ui/`.

## Données vaisseaux
`src/Domain/Data/ships.json` : 106 vaisseaux extraits du bundle JS de sc-cargo.space (lu comme DONNÉES, sans exécuter le code :
littéraux JS convertis en JSON). Format : `{slug, manufacturer, name, capacity, blocks:[{x,y,z,w,l,h,maxSize?}]}`.
Axes convertis : source (x largeur, y hauteur, z profondeur) → chez nous (x largeur, y longueur, z hauteur).
Vérifié : somme des volumes des blocs = capacité, aucun chevauchement. Non vérifié : fidélité au jeu ; le **côté de la porte**
n'est pas dans les données (défaut `DoorSide::Rear`, surchargeable par `Ship::withDoor()`). Slugs : `drake-caterpillar`,
`crusader-c2-hercules`, `drake-ironclad`… (défaut : `ShipCatalog::DEFAULT`). Ne pas réintroduire de capacités « de mémoire ».

## Pièges connus
- **Tom Select + Live Component** : Tom Select ne fait pas remonter ses événements `change` (le contrôleur re-déclenche un
  `change` qui bulle) et `destroy()` remet le `<select>` à son HTML initial lors d'un déplacement morphdom (le serveur injecte
  `data-tom-select-current-value`). Les missions/lots ont un `uid` stable pour garder leurs nœuds DOM après `array_values`.
- La cohérence d'une mission est validée (`Mission::validate()`) : le type doit correspondre aux lots, sinon
  `InvalidArgumentException` (message FR). Attraper aussi `DomainException` (pas de place / tour infaisable) et les afficher.
- Les ids de caisses sont `{lotId}-{n}` : stables tant que les lots ne changent pas (les `Pin` en dépendent).

## État
Fait : socle Symfony/Docker, domaine complet (paliers, types de mission, blocs, pins), 106 vaisseaux, Tom Select partout.
En cours / à faire (voir `.claude/agents/`) : rendu de la soute par blocs, sauvegarde `localStorage`, réduction du lag UI,
glisser-déposer des caisses + choix de la porte, formulaire par type de mission, persistance Doctrine (optionnel), premier commit/push.
Rien n'est encore commité.

## Conventions
KISS, classes `final readonly` quand possible, types stricts, peu de commentaires. Ne rien commiter/pousser sans demande explicite.
Répartition des fichiers entre agents : voir `.claude/agents/` (pas de chevauchement).
