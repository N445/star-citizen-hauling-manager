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
    docker compose exec -T php vendor/bin/phpunit tests/Service
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
  Lieux et ressources restent des chaînes libres dans `Delivery`. Suggestions : entité `Location {name, ?parent}` (arbre, sans parent = système ;
  nom unique parmi les frères ; Stanton, Pyro, Nyx semés par migration, à fiabiliser), lue par le service `LocationCatalog`
  (seules les feuilles sont proposées, groupe Tom Select = 2 premiers niveaux « Stanton › Hurston », le reste en détail ;
  cache remis à zéro par `ResetInterface` en mode worker) et l'entité `Commodity {name, color}` (40 ressources semées par migration).
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
  l'import), ressources (nom, couleur), lieux (un niveau à la fois : systèmes, puis « Ouvrir » liste les enfants ; niveau
  porté par le paramètre `parentId` qu'EasyAdmin garde dans ses liens ; supprimer un lieu supprime ses descendants). Écritures via les méthodes métier (options `getter`/`setter` des champs), aucun
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
  ramassage multiple toute la cargaison est **réservée** dès le premier ramassage (`TaskList::loadOf`, prudent pour la
  capacité), mais la charge affichée par arrêt (`Stop::load` = `TaskList::aboardOf`) ne compte que les lieux déjà ramassés.
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
  l'import ; Caterpillar = gauche + droite, cf. Portes cargo) est la plus proche de la cible « façon livreur » : rang de sa livraison parmi les
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

- **Portes cargo** : `Ship::doorSides` = portes du vaisseau (plusieurs possibles), `Ship::mainDoor` = la principale (toujours
  l'une d'elles ; admin : champ « Porte principale »). Une tournée utilise **sa porte principale seule** par défaut ;
  `Tour::doorSides` (JSON nullable) est la surcharge du joueur, choisie dans le formulaire de tournée sans toucher au
  vaisseau (`Tour::overrideDoors` remet null si le choix égale le défaut ; `Tour::doorSides()` donne les portes effectives,
  utilisées par `CargoPlanner`, `RoutePanel` et les tests). Données connues : `ShipDoorCatalog` (seul le Caterpillar,
  gauche + droite, principale gauche ; tout le reste = arrière, à corriger dans l'admin), appliqué aux vaisseaux neufs par
  `ShipImporter` et à tous par `bin/console app:ships:doors`.
- **Première installation** : `make init` = `up` + import des vaisseaux (réseau requis) + portes connues. Lieux et ressources
  viennent des migrations (une seule migration de base `Version20261106170000`, puis les suivantes : toute base migrée avant
  cette fusion doit voir sa table `doctrine_migration_versions` réécrite à la main).
- **Statistiques Umami** (auto-hébergé) : `UMAMI_URL` + `UMAMI_WEBSITE_ID` dans `.env.local` / l'environnement de prod ;
  vides = rien n'est émis. Le navigateur ne parle qu'à notre domaine : `StatsProxyController` sert le script sous `/_s/s.js`
  (récupéré chez Umami, en cache 1 h, chemin `/api/send` renommé `/c` contre les bloqueurs de pub) et relaie `POST /_s/c`
  vers `{UMAMI_URL}/api/send` avec l'adresse (dernier `X-Forwarded-For`) et le User-Agent du visiteur ; événement refusé si
  `website` ≠ `UMAMI_WEBSITE_ID`. Événements : attribut `data-track="nom"` (+ `data-track-on="change"`,
  `data-track-props='{...}'`) sur n'importe quel élément, écouté par délégation dans `assets/stats.js` (survit aux rendus
  Live Component) ; en JS : `track('nom')`. Posés : `tour-create/save/new`, `mission-add/update/edit`, `route-mode`,
  `route-reorder`, `route-reset-order`, `stop-toggle`, `doors-change`, `cargo-reset-view`, `tour-tab`, `locale-switch`,
  `theme-switch`, `login-submit`, `register-submit`, `contact-submit` (clics/envois, pas résultats validés).

## Fonctionnalités ajoutées depuis (elles priment sur les descriptions plus anciennes ci-dessus)
- **Édition manuelle de la soute** : `src/Service/Cargo/` `CargoEditor` (`move`, `swap` à taille égale, `markPlaced`, `reset`),
  `PlacementRules` (forme du jeu et rotation, dans une grille, `minSize`/`maxSize`, pas de chevauchement, caisse soutenue
  sur toute sa surface), `PlacementException` (clés `cargo.edit.error.*`). Les mêmes règles existent côté navigateur dans
  `assets/cargo-rules.js` (aperçu vert/rouge) : **toute règle modifiée l'est aux deux endroits**. Le serveur valide
  contre les caisses visibles à l'étape courante puis fige la mission entière dans `Mission::cargoPlacement` avec les
  marqueurs `manual` et `placed`. Actions Live de `RoutePanel` : `moveCrate`, `swapCrates`, `markCrate`, `resetPlacement`
  (arguments scalaires, un refus part au navigateur par l'événement `cargo:rejected`). Interaction dans
  `cargo-grid_controller.js` : clic = sélection, glisser = déplacer (OrbitControls coupé pendant le glisser), R = pivoter,
  flèches = décaler, Ctrl+Z = annuler, Maj = pas d'échange, Entrée/V = « Posée ✓ » (caisse validée : ne bouge plus, marqueur
  ✓, compteur n/m). Le placement manuel survit au décochage du ramassage ; effacé si la mission est modifiée ou au reset.
  Au chargement, `CargoPlanner` remet les caisses figées du bas vers le haut (sinon une caisse posée à la main flotte).
- **Surbrillance verrouillable** : clic sur mission/livraison/ressource/étape/caisse 3D fige la surbrillance (2e clic, vide
  ou Échap la lève) ; l'événement `cargo:highlight` porte `locked`. Caisses surlignées qui clignotent, les autres
  atténuées ; `prefers-reduced-motion` = atténuation fixe. Bordure des caisses = couleur de la ressource ; vert/orange/rouge
  ne sont plus que des halos d'état (à charger / à décharger / empilée).
- **Ramassage multiple réparti** : `Mission::pickupShares(ordre)` = SCU chargés par lieu (quantité saisie, sinon le reste de
  l'estimation au **premier lieu sans quantité** ; hors `MultiplePickUp` à une livraison, tout au premier ramassage) ;
  `Mission::quantityLoadedFrom(lieux)` = même règle sans ordre (affichage de la charge). `CargoPlanner` charge chaque part à
  son arrêt ; chaque caisse figée porte son lieu (`pickup` dans `cargoPlacement`) et n'est replacée qu'à cet arrêt (ancien
  format sans `pickup` = premier lieu ; plus de SCU figés que la part = placement ignoré, recalculé). Bug corrigé : 2 + 10 SCU
  saisis, les 12 montaient au premier ramassage.
- **Quantités réelles (SCU)** : `Delivery::actualQuantity`, `Mission::pickupQuantities`, `effectiveQuantity()` (remplace
  l'estimation dans `TaskList` et `CargoPlanner`), service `QuantityEditor` (entier 0–10000, total ≤ capacité du vaisseau),
  actions `RoutePanel::setDeliveryQuantity|setPickupQuantity`. L'ordre des arrêts est **figé avant** d'enregistrer une
  quantité (saisir ne réordonne jamais). Changer une quantité efface les emplacements figés de la mission ; modifier la
  mission remet tout à zéro. Interface : un **badge noir discret à la place de la quantité affichée** (jamais en plus), un
  clic = champ de même taille, Entrée/blur enregistre, Échap annule (`_scu_badge.html.twig`, `quantity_controller.js`).
  Le ramassage n'a un badge que si la mission a une seule livraison ; sinon quantités de livraison en texte simple.
  Piège Twig : `include` transmet tout le contexte, passer `with_context = false` aux partiels qui testent `is defined`.
- **Signalement d'erreur** : icône drapeau dans l'en-tête de la route, modale (`route-report_controller.js`), entité
  `RouteReport` (message, catégorie, statut ouvert/résolu, `snapshot` JSON figé : missions, quantités, vaisseau, portes,
  route générée, plan de soute, locale ; jamais d'email), `src/Service/Report/` (`RouteSnapshot`, `RouteReporter` : 30 s mini
  entre deux envois et 5 par heure par compte/jeton invité, `ReportReplay`), `bin/console app:reports:replay <id>`. Admin :
  `RouteReportCrudController` en consultation + `RouteReportStatusController` (résoudre/rouvrir, CSRF).
- **Admin des lieux en arborescence** : `LocationTreeController` (`/admin/locations`, pages racine et lieu avec fil d'Ariane,
  arbre repliable `assets/location-tree.js`, ajout simple ou en lot « tout ou rien », renommer/déplacer/supprimer),
  `src/Service/Location/LocationEditor` (nom unique parmi les frères sans tenir compte de la casse, pas de cycle, suppression
  en cascade). Remplace l'ancien `LocationCrudController`.
- **Tableau de bord admin** : `/admin` (`DashboardController::index`, `templates/admin/dashboard.html.twig`) = récap global
  calculé par `src/Service/Admin/AdminOverview` (une requête agrégée par chiffre : comptes, tournées avec compte / anonymes /
  orphelines, missions en cours / terminées, aUEC et SCU livrés, signalements et messages ouverts, top 5 vaisseaux,
  ressources, lieux de livraison, dernières inscriptions et messages). Menu : badges du nombre de signalements et messages
  ouverts. Auteur d'une tournée sans compte affiché **« Anonyme »** (`tour.guest`), sans auteur ni jeton = « orpheline ».
- **Page de contact** `/contact` (route `contact`) : retours d'amélioration, bugs, questions. `ContactController` +
  `ContactFormType` (DTO `ContactData`, Turnstile via `TurnstileType`), entité `ContactMessage` (catégorie `ContactCategory`,
  message, e-mail de réponse **facultatif** pré-rempli pour un compte, locale, statut `ReportStatus` réutilisé, auteur ou
  jeton invité — le jeton est émis si absent). Anti-abus `Service/Contact/ContactSender` : 60 s mini entre deux messages,
  5 par heure par compte/jeton (refus = 429 + erreur de formulaire). Admin : `ContactMessageCrudController` (consultation,
  suppression) + `ContactMessageStatusController` (traiter/rouvrir, CSRF `admin_contact_status`). À la suppression du compte, ses messages **restent, rendus anonymes** (choix de l'utilisateur) : `UserAccounts::delete` → `ContactMessageRepository::anonymizeAuthor` (auteur et e-mail effacés), FK `SET NULL` en filet ; la suppression d'un utilisateur dans l'admin passe aussi par `UserAccounts::delete`.
- **Pages légales (RGPD)** : `/legal` (mentions légales) et `/privacy` (confidentialité), `LegalController` ; textes longs en
  gabarits par langue `templates/legal/{notice,privacy}.{fr,en}.html.twig` (pas en clés de traduction). Éditeur « N445 » et
  hébergement « serveur personnel de l'éditeur, situé en France » écrits en dur, sans variable d'environnement : **choix de
  l'utilisateur** (auto-hébergé, ne pas publier d'adresse personnelle ; risque LCEN jugé faible et accepté). Liens dans le **pied de page**
  (`base.html.twig` : contact, mentions, confidentialité, avertissement « site de fan » ; toujours en bas de l'écran : `body` en flex colonne + `margin-top: auto`). La politique décrit cookies
  (`PHPSESSID`, `hauling_guest`, `hm-theme` en localStorage), Umami, Turnstile et les durées promises (signalements et
  messages : au plus 2 ans) : **toute nouvelle donnée ou nouveau service tiers doit y être ajouté** (fr + en).
- **Sitemap dynamique** : `SitemapController` sert `/sitemap.xml` (toutes les routes marquées `options: ['sitemap' => true]` :
  accueil, login, inscription, contact, légal) et `/robots.txt` (exclut `/admin`, `/tours`, `/history`, `/profile`…). Une
  nouvelle page publique sans paramètre = ajouter l'option sur sa route ; jamais les tournées (privées).
- **Admin ressources** : sélecteur de couleur avancé Pickr (`assets/admin.js`, entrée Encore `admin`, chargée par
  `DashboardController::configureAssets`).
- **Listes déroulantes** : Tom Select trie par ordre alphabétique strict (`sortField` + `score` constant : sinon la
  pertinence prime et « hur » ramène HUR-L1 avant les noms longs). Vaisseaux triés par nom puis constructeur.
- **Production** : `make update` (CD) = pull + up + `init` (migrations puis import des vaisseaux, idempotent : upsert par
  slug). `app:ships:doors` n'y est volontairement **pas** lancé (il écrase les portes corrigées dans l'admin) ; l'import
  qui échoue ne fait pas échouer un déploiement déjà en ligne (avertissement). Sessions PHP : volume nommé `sessions_data`
  monté sur `/sessions` (`save_path` en prod ; `var/` est un tmpfs vidé à chaque mise à jour, d'où des déconnexions).
  **Pas de sessions en base** (choix de l'utilisateur).
- **Tests** : `.env.test` utilise SQLite en mémoire pour les tests fonctionnels (`tests/Admin/LocationTestCase`, schéma
  recréé à chaque test) ; `tests/Twig/` rend vraiment les gabarits (un lint ne voit pas les erreurs d'exécution : ajouter un
  test de rendu pour tout nouveau partiel).

## Environnement de travail (WSL)
- Le projet vit dans **Ubuntu WSL2** : `~/star-citizen-hauling-manager` (Windows : `\\wsl.localhost\Ubuntu\home\robin\...`).
  Le dossier `C:\dev\projects\star-citizen-hauling-helper` est une ancienne copie : **ne plus y travailler** (conflits de
  merge déjà arrivés ; en cas de doute, **le code Linux est prioritaire**). Sur l'hôte Windows `make` n'existe pas : lancer
  les commandes via `wsl -d Ubuntu -e bash -lc 'cd ~/star-citizen-hauling-manager && make ...'`.
- **Toujours passer `.env.local`** à `docker compose` (le Makefile le fait : `make start`, `make up`) ; sinon le conteneur se
  rattache au mauvais réseau Traefik. Traefik est dans `~/project/traefik` (réseau `traefik`), à lancer avec
  `docker compose up -d` ; site sur http://sc-hauling-manager.local et http://localhost:8000.
- Fins de ligne LF (`.gitattributes`) : normaliser un `Dockerfile`/`compose*.yaml` en CRLF avant de l'éditer par motif.

## Tests de philosophie
`tests/Philosophy/` (~1 170 tests, ~6 s) : invariants du parcours et de la soute sur ~350 cas générés à graine fixe
(vraies grilles figées dans `Fixtures/ships.json` + grilles synthétiques) et des scénarios écrits à la main. Ils
vérifient des principes, pas l'implémentation ; ids des entités posés par réflexion (`IdSequence`). Avant de
« corriger » un échec, vérifier si c'est le test ou le code qui a tort.

## Pièges connus
- **Derrière Traefik** : `framework.trusted_proxies` = `%env(TRUSTED_PROXIES)%` (`private_ranges` dans `.env`) + en-têtes
  `X-Forwarded-*`. Sans cela Symfony se croit en HTTP sous HTTPS : URL `http://` (barre de debug bloquée en « contenu mixte »,
  sitemap, redirections). Ne pas publier le port 8000 du conteneur en prod (en-têtes falsifiables depuis un réseau privé).
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
Fait ensuite : tests de philosophie, placement et édition manuelle des caisses, surbrillance verrouillable, quantités réelles,
signalement d'erreur, admin des lieux en arbre, sélecteur de couleur, sessions persistantes en prod, CD avec `init`,
tableau de bord admin, page de contact (Turnstile), pages légales RGPD + pied de page, sitemap/robots dynamiques.
À faire : purge des vieilles tournées invité et des messages/signalements
de plus de 2 ans (promis par la politique de confidentialité), `login_throttling` (rate-limiter absent), vérifications navigateur restantes
(glisser jusqu'à un dépôt valide, échange, rotation, annulation, tactile, français, thème clair), CSRF de l'action Live `report`.

## Conventions
- **KISS** et **Object Calisthenics** : un seul niveau d'indentation par méthode, pas de `else`, tout type primitif métier
  enveloppé dans un objet (value object), collections de première classe, une seule `->` par ligne quand c'est possible,
  pas d'abréviations, petites classes (≈ 50 lignes) et méthodes courtes, au plus deux propriétés d'instance par classe
  quand c'est raisonnable, pas de getters/setters génériques (méthodes métier : `rename()`, `moveTo()`…).
- **Code linéaire** (garde-fous / *early return*, aussi appelé « happy path à plat ») : on traite d'abord les cas d'erreur
  ou limites par un `return`/`throw` immédiat, puis le cas nominal reste en bas, sans imbrication ni `if` profond ; pas de
  `else` après un `return`.
- Classes `final readonly` quand possible, types stricts, peu de commentaires (seulement le « pourquoi » non évident).
- Ne rien commiter/pousser sans demande explicite ; ne jamais faire de `git stash` (il emporte le travail des autres agents).
- **Maintenir les fichiers Claude à jour** : après toute modification qui change le comportement, l'architecture, une
  commande, un piège ou une décision de l'utilisateur, mettre à jour `CLAUDE.md` (section concernée, et « État »), et
  `.claude/agents/*.md` si la répartition ou les règles des agents changent. Retirer ce qui devient faux plutôt que
  d'empiler. Les décisions explicites de l'utilisateur (ex. « pas de sessions en base », « le code Linux est prioritaire »)
  y sont consignées avec leur raison.
- Tester vraiment : un lint ne remplace pas un test de rendu ; dire précisément ce qui a été vérifié de bout en bout.
- Répartition des fichiers entre agents : voir `.claude/agents/` (pas de chevauchement) ; un seul agent à la fois sur les
  mêmes fichiers (`RoutePanel`, `Mission`, `templates/tour/*`, `DashboardController`).
