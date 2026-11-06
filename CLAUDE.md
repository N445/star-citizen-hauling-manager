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
PHP 8.4, Symfony 7.4 LTS, Doctrine/MariaDB 11.4, formulaires Symfony + Live Component, Bootstrap 5, Webpack Encore + Stimulus,
Tom Select, PHPUnit. Interface en anglais par défaut et en français (POC fonctionnel, pas de design pour l'instant).

## Lancer / tester (tout passe par Docker, PHP n'est pas installé sur l'hôte)
    make up                                                    # http://sc-hauling-manager.local (Traefik) ou :8000
    make test                                                  # lint + toute la suite
    make watch                                                 # Encore en mode watch
    make examples                                              # (re)crée les tournées d'exemple ; un seul : bin/console app:examples:load 5
    docker compose exec -T php vendor/bin/phpunit tests/Domain
    docker compose exec -T php bin/console lint:twig templates

- Image FrankenPHP multi-stage `docker/Dockerfile` : `dev` (code monté, uid de l'hôte) et `prod` (code + assets compilés,
  mode worker). Ne pas remettre `APP_ENV` dans l'image dev : il écraserait le `test` de PHPUnit.
- CI : `.github/workflows/tests.yml` ; CD : `build.yml` publie l'image sur GHCR depuis `main`. Prod : `compose.prod.yaml`
  + `Makefile.prod` (Traefik, conteneur en lecture seule, MariaDB).

## Architecture (refonte de zéro, novembre 2026 : fonctionnalités d'abord, interface ensuite)
- `src/Entity/Ship` : vaisseau importé de sc-cargo.space — slug unique (`drake-caterpillar`…), constructeur, nom, capacité
  SCU, `cargoGroups` (JSON) = grille **officielle** telle que la source la décrit : groupes `{name?, x, z, minSize?, grids}`,
  grilles `{name?, x, y, z, width, height, length, minSize?, maxSize?, preferHorizontal?}`, axes source x largeur,
  y hauteur, z longueur, positions parfois fractionnaires (`.5`). Somme des volumes = capacité pour les 106 vaisseaux.
- `src/Entity/Tour` : nom, `shipName` + `capacity` (saisis, éditables) et `?Ship` (null = vaisseau hors catalogue, pas de
  grille). `Mission` : nom optionnel, récompense aUEC, `MissionType`, `pickups` (liste JSON de lieux), collection de
  `Delivery {dropoff, resource, quantity}`. Types = règle de comptage (`MissionType::accepts`) : direct 1→1, livraisons
  multiples 1→N, ramassages multiples N→1 (quantité = total, répartition par lieu inconnue, comme dans le jeu).
  Lieux et ressources restent des chaînes libres dans `Delivery`. Suggestions : `src/Catalog/LocationCatalog` (système → planète → lieu → lune, groupes Tom Select « Stanton › Hurston » ; lieux de
  Stanton saisis de mémoire, à fiabiliser) et l'entité `Commodity {name, color}` (40 ressources semées par migration).
  `CommodityColors` / `commodity_color()` donne la couleur, gris `#6c757d` pour une ressource hors base. Affichage :
  `_commodity_badge.html.twig` (fond 15 %, bordure pleine, texte assombri) ; Tom Select : `data-tom-select-badges-value`.
- **Langues** : anglais par défaut, français ; locale en session (`/locale/{en|fr}`, public, retour au referer du même
  hôte), appliquée à chaque requête par `SessionLocaleSubscriber` (Live Components compris, pas de préfixe d'URL).
  Traductions YAML ICU : `messages` et `validators` (site), `account` (comptes). Les enums (`MissionType`, `DoorSide`,
  `RouteMode`) renvoient des **clés** depuis `label()/rule()/description()` (à passer par `|trans`) ; `MissionType::shortLabel()` (sans le
  schéma « A → B ») pour l'affichage, `label()` seulement dans la liste déroulante du formulaire ; les erreurs du
  parcours sont des `RouteException` (traduisibles, sous-classe de `\DomainException`). Les données (lieux, ressources,
  vaisseaux, noms de missions) et la console ne se traduisent pas.
- **Sans compte (mode invité)** : le site est utilisable sans connexion. La page d'accueil `/` (route `home`) EST la
  tournée en cours : invité → sa tournée unique, retrouvée par le jeton aléatoire de son navigateur (`Security\GuestToken`,
  cookie `hauling_guest` d'un an — cookie et non localStorage car le serveur doit le recevoir à chaque requête, Live
  Components compris ; `Tour::guestToken`) ; compte → sa tournée la plus récente. « Nouvelle tournée » (`/tours/new`) :
  l'invité REMPLACE la sienne (`TourWriter::createForGuest`), un compte en ajoute une. À l'inscription comme à la
  connexion, la tournée invité passe sur le compte (`GuestTourTransfer`, écouteur `LoginSuccessEvent`). `TourVoter` :
  propriétaire, ou détenteur du jeton pour une tournée sans propriétaire. Pas encore de purge des vieilles tournées
  invité.
- **Comptes** : `User` (email unique en minuscules, rôles, `registeredAt`), inscription `/register` (`Security\UserRegistration`
  connecte directement), `/login` (form_login, CSRF stateless : curl doit envoyer `Origin`), `/logout`. Accès :
  `/admin` → `ROLE_ADMIN`, `/history` et `/tours` (liste) → `ROLE_USER`, tout le reste public (protégé par `TourVoter`,
  y compris dans les Live Components via `#[PostHydrate]` dans `MissionBoard` et `RoutePanel`). Tournées orphelines :
  `bin/console app:tours:claim <email>` ; exemples : `app:examples:load --user=<email>`. `/history` : missions terminées
  de l'utilisateur, avec la durée du chrono caché (`Mission::startedAt/finishedAt`, posés par `ProgressTracker`) et les
  totaux. `/profile` : changer l'email ou le mot de passe (mot de
  passe actuel exigé ; `ProfileEditor` reconnecte aussitôt, sinon Symfony déconnecte), supprimer le compte. **Supprimer un
  compte supprime tout** (tournées, missions, historique : `UserAccounts::delete`, utilisé aussi par `app:user:delete`).
  Emails normalisés en minuscules avant validation (`Form\EmailNormalizer`). Textes du domaine de traduction `account`. Pas de `login_throttling` tant que `symfony/rate-limiter` n'est
  pas installé.
- **Admin** : EasyAdmin sur `/admin` (`ROLE_ADMIN`, `src/Controller/Admin/`) : utilisateurs (rôle admin, nouveau mot de
  passe), tournées en consultation seule (liste + fiche avec toutes ses missions, `templates/admin/tour/missions.html.twig` ;
  suppression possible, ni création ni édition ; pas de CRUD mission), vaisseaux (portes cargo ; ni création ni suppression, ils viennent de
  l'import), ressources (nom, couleur). Écritures via les méthodes métier (options `getter`/`setter` des champs), aucun
  setter générique. Libellés : domaine `admin`. Filtres sur chaque CRUD ; rôles et portes via `JsonContainsFilter` (l'ArrayFilter
  d'EasyAdmin 5.9 plante). Comptes en console : `app:user:create|edit|delete` (questions
  interactives, ou arguments/options avec `-n`) — c'est le seul moyen de créer un admin.
- Services fonctionnels dans `src/Service/` : `ScCargo/` (import) et `Tour/` (`TourWriter`, `MissionWriter`).
- Page unique d'une tournée `/tours/{id}` : formulaire classique de la tournée (nom, vaisseau, SCU) + Live Component
  `MissionBoard` (`src/Twig/Components`) : un formulaire de mission en haut (`LiveCollectionType` pour les livraisons),
  liste compacte dessous, actions `save`/`edit`/`cancel`/`remove`. Les missions sont modifiées sur place (ids stables).
  Formulaires = DTO à propriétés publiques (`src/Form/Data/*Data`, validation + `fromX()`/`toX()`/`applyTo()`).
- **Parcours** (`src/Service/Route/`, PHP pur) : `RoutePlanner::plan(Tour, RouteMode): Route`.
  `TaskList` transforme les missions en tâches localisées indexées en bits (ramassage par mission et par lieu, une
  tâche par livraison) ; une livraison n'est possible qu'une fois **tous** les ramassages de sa mission faits ; en
  ramassage multiple toute la cargaison compte à bord dès le premier ramassage (prudent pour la capacité).
  `RouteSearch` = A* sur (lieu, tâches faites), 1 par arrêt, heuristique = lieux restant à visiter. À chaque arrêt on
  livre tout, puis la `StopPolicy` du mode ramasse : `FewestStopsPolicy` (tout ce qui rentre ; quand la capacité force
  un choix, une option par mission prioritaire, sinon le glouton faisait plus d'arrêts que le mode mission) ou `MissionFocusPolicy` (une mission à la fois ; plusieurs états possibles = choix de la mission
  à démarrer). Départ = `Tour::startLocation` (optionnel). Pas de distances : seul le nombre d'arrêts compte.
  Mesuré : < 0,2 s jusqu'à 10 missions, ~1 s à 12 en mode mission par mission ; garde-fou à 300 000 expansions.
  **Ordre manuel** : glisser-déposer (SortableJS, `route-sortable_controller.js` → action Live `reorder`) enregistre
  `Tour::stopOrder` (lieux hors départ) ; `RouteReplay` rejoue cet ordre avec la même `StopPolicy` : programmation dynamique
  mémoïsée sur les choix (plus court, puis le moins d'arrêts sautés ; impasses écartées), arrêts inutiles sautés, puis
  `RouteSearch::continueFrom`. Garantie testée : reposer l'ordre calculé redonne le même parcours. « Recalcul
  auto » remet `stopOrder` à null. **Avancement** : `Delivery::delivered`, `Mission::pickedUpAt` (lieux ramassés) ;
  cases à cocher dans le parcours, mission grisée « Terminée » à gauche. Le parcours est toujours calculé depuis le
  départ ; **à la première case cochée, l'ordre affiché est figé** dans `stopOrder` (`RoutePanel::freezeStopOrder`) et
  les modes sont désactivés : cocher ne fait plus rien bouger (retour utilisateur : les cartes « Fait » qui remontaient
  et la suite recalculée étaient impossibles à suivre). L'étape courante = premier arrêt non terminé. Modifier une mission remet ses livraisons à « non livrée ».
  Live Component `RoutePanel` (mode en `LiveProp` modifiable), recalculé sur l'événement `missionsChanged` émis par
  `MissionBoard` ; `MissionBoard` se rafraîchit sur `progressChanged`. Erreurs (`DomainException`) affichées via `this.error` (pas `error` : le contexte Twig est figé avant
  le calcul).
- **Chargement de la soute** (`src/Service/Cargo/`) : `CargoPlanner` (instancié à chaque calcul, il a un état) rejoue
  « Fait » + suite du parcours : à chaque arrêt il décharge puis charge les caisses (`CrateSize` : 1/2/4/8/16/24/32
  SCU, dimensions L×l×h du jeu, découpage glouton puis moitié si pas de place, `minSize`/`maxSize` par grille) au
  point libre et soutenu dont la profondeur (0 = porte, 1 = fond, depuis la porte la plus proche parmi
  `Ship::doorSides`, plusieurs possibles, saisies par l'utilisateur car absentes de sc-cargo.space et conservées à
  l'import ; Caterpillar = gauche + droite) est la plus proche de la cible « façon livreur » : rang de sa livraison parmi les
  livraisons encore à venir (la prochaine → porte, la dernière → fond). Priorités du choix (`CargoSpace::score`) :
  1) ne pas être **posée sur** une caisse livrée avant, 2) ne pas lui couper l'accès depuis **toutes** les portes,
  3) près des caisses de **même destination** s'il y en a à bord (sinon la profondeur cible), 4) le plus bas. Si poser dessus est inévitable, on tente deux demi-caisses ; sinon la caisse est marquée
  (`stackedOnEarlierDelivery`) et le panneau l'avertit. Au ramassage coché, les
  emplacements proposés sont **figés** dans `Mission::cargoPlacement` (sinon une caisse à bord bougerait à chaque
  recalcul du trajet) ; effacés si on décoche, si la mission est modifiée, ou ignorés s'ils ne tiennent plus. Étape courante = premier arrêt non fait ; on y montre les caisses
  à bord (pleines), à charger (fantômes jusqu'au ramassage coché), à décharger (contour orange, disparaissent une fois
  livrées). Données passées à la 3D par `cargo-plan_controller.js` (valeur JSON dans `RoutePanel`, événement
  `cargo:plan`). Hypothèse non vérifiée : z = 0 est l'avant du vaisseau (la flèche de porte permet de contrôler).
- **Surbrillance croisée** : `data-highlight-key` (ce que l'élément désigne) / `data-highlight-tokens` (ce qui l'allume),
  jetons `HighlightToken` (`mission-`, `delivery-`, `resource-`, `step-`) ; `highlight_controller.js` sur `<body>` et la
  3D (lancer de rayon sur les caisses) échangent l'événement `cargo:highlight`.
- Piège : les méthodes publiques d'un Live Component sont des actions de contrôleur, leurs arguments sont autowirés
  (une méthode publique typée `Route` casse le conteneur) : pas d'argument objet sur les méthodes publiques.
- Front : `tom-select_controller.js` (création libre partout, ré-émet un `change` qui bulle), `ship-capacity_controller.js`
  (SCU pré-rempli, éditable), `cargo-grid_controller.js` (soute en 3D three.js + OrbitControls : sol gris de chaque grille, double flèche orange au sol côté porte, caisses ; rendu à la demande ; suit le champ `[data-ship-name]`).

## Tests de philosophie
`tests/Philosophy/` (~1 170 tests, ~6 s) : invariants du parcours et de la soute sur ~350 cas générés à graine fixe
(vraies grilles figées dans `Fixtures/ships.json` + grilles synthétiques) et des scénarios écrits à la main. Ils
vérifient des principes, pas l'implémentation ; ids des entités posés par réflexion (`IdSequence`). Avant de
« corriger » un échec, vérifier si c'est le test ou le code qui a tort.

## Pièges connus
- **Tom Select dans le Live Component** : chaque widget est enveloppé dans `data-live-ignore` (sinon le re-rendu détruit
  le DOM de Tom Select), avec un id qui inclut `formVersion` : incrémenté à chaque formulaire neuf, il force la
  recréation des champs (sinon une mission validée laisserait ses valeurs). Les erreurs restent hors de l'enveloppe.
- Formulaire de mission progressif (type → nom/récompense/ramassages → livraisons) : les champs masqués ne sont pas rendus
  (`render_rest: false`), le Live Component garde leurs valeurs. La règle du type est une violation **à la racine** du
  formulaire : la validation en direct n'efface que celles-là, elle ne s'affiche donc qu'au clic sur « Valider ».
  En Twig, `default` traite `''` comme absent : passer `mb-0` pour supprimer une marge, pas une chaîne vide.
- **Cases à cocher dans un Live Component** : chacune a un `id` unique lié à son action, sinon le re-rendu réutilise
  le nœud par position et sa propriété `checked` (pas l'attribut) s'affiche sur une autre action.
- Tom Select écarte au démarrage toute valeur absente des options : le contrôleur ajoute la valeur courante aux options.
- Le parseur dépend du format minifié de sc-cargo.space : chaque vaisseau est un
  `Object.freeze(Object.defineProperty({__proto__:null,…,manufacturer:X,name:Y,official:Z}))`. Les identifiants sont
  résolus par leur **dernière** déclaration avant le module (les noms minifiés sont réutilisés ailleurs). Littéraux JS
  convertis : clés non quotées, `!0`/`!1`, `.5`, et `1e3` (capacité du Pioneer) d'où le cast `(int)`. S'il ne trouve rien,
  il lève une exception : le cron doit échouer bruyamment, pas vider le catalogue.
- Les données `unofficial` (grilles alternatives) et `labels` sont ignorées.

## État
Fait : socle Docker/Traefik, CI/CD GHCR, import sc-cargo.space, tournées + missions (page unique `/tours/{id}` : formulaire 3 | soute 6 | parcours 3, missions en Live Component), calcul du parcours (2 modes), profiler en dev.
À faire : tests (moteur de route en priorité), placement des caisses dans la grille, puis l'interface. `.claude/agents/` décrit l'ancienne architecture (obsolète).

## Conventions
KISS, classes `final readonly` quand possible, types stricts, peu de commentaires. Ne rien commiter/pousser sans demande explicite.
Répartition des fichiers entre agents : voir `.claude/agents/` (pas de chevauchement).
