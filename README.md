# Star Citizen Hauling Manager

Tour de pickup/drop sans aller-retours inutiles, vu par mission, avec la soute du vaisseau en surbrillance.
Stack : PHP 8.4, Symfony 7.4, Symfony UX Live Component. Tout tourne sous Docker.

## Lancer en local
    docker compose up -d php        # http://localhost:8000
    docker compose logs -f php      # logs
    docker compose down             # arrêt

Première fois (ou après un pull) :

    docker compose run --rm --no-deps php composer install

## Commandes utiles
    docker compose exec php vendor/bin/phpunit
    docker compose exec php php bin/console lint:twig templates

Architecture : `docs/SPEC.md`.
