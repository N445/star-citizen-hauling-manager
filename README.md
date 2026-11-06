# Star Citizen Hauling Manager

Tour de pickup/drop sans aller-retours inutiles, vu par mission, avec la soute du vaisseau en surbrillance.
Stack : PHP 8.4, Symfony 7.4, Doctrine/MariaDB 11.4. Tout tourne sous Docker.

## Lancer en local
Prérequis : le Traefik local (réseau `main_traefik`) et `127.0.0.1 sc-hauling-manager.local` dans `/etc/hosts`.

    make up          # build + démarrage + composer install + migrations → http://sc-hauling-manager.local (ou :8000)
    make test        # lint + phpunit
    make sh / logs / down

## CI/CD
- `.github/workflows/tests.yml` : lint + PHPUnit sur chaque PR et branche.
- `.github/workflows/build.yml` : sur `main` (et tags `v*`), tests puis image prod publiée sur
  `ghcr.io/n445/star-citizen-hauling-manager:{main|<sha court>}`. `make build-prod` produit la même en local.
- Serveur : `compose.prod.yaml` (→ `compose.yaml`) + `Makefile.prod` (→ `Makefile`) + un `.env` avec `APP_SECRET`,
  `APP_DOMAIN`, `DB_PASSWORD` et `DB_ROOT_PASSWORD`, puis `make update`.

Catalogue des vaisseaux : `make ships`, import depuis sc-cargo.space, à mettre en cron sur le serveur.
