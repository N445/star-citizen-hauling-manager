ENV_FILES := --env-file .env $(if $(wildcard .env.local),--env-file .env.local)
DC := docker compose $(ENV_FILES)

IMAGE ?= $(shell sed -n 's/^IMAGE=//p' .env)
TAG   ?= $(shell git rev-parse --short HEAD)

.PHONY: examples watch up start down logs sh install ships test lint phpunit build-prod

up: start install

start:
	$(DC) up -d --wait

down:
	$(DC) down

logs:
	$(DC) logs -f

sh:
	$(DC) exec php bash

install:
	$(DC) exec -T php composer install
	$(DC) exec -T php yarn install
	$(DC) exec -T php yarn build
	$(DC) exec -T php bin/console doctrine:migrations:migrate -n --allow-no-migration

watch:
	$(DC) exec php yarn watch

ships:
	$(DC) exec -T php bin/console app:ships:import

examples:
	$(DC) exec -T php bin/console app:examples:load

test: lint phpunit

lint:
	$(DC) exec -T php composer validate --no-check-publish
	$(DC) exec -T php composer audit
	$(DC) exec -T php bin/console lint:yaml config
	$(DC) exec -T php bin/console lint:twig templates
	$(DC) exec -T php bin/console lint:container
	$(DC) exec -T php bin/console doctrine:schema:validate

phpunit:
	$(DC) exec -T php vendor/bin/phpunit

build-prod:
	docker build -f docker/Dockerfile --target prod --provenance=false --sbom=false \
	  --build-arg VERSION=$(TAG) --build-arg REVISION=$(shell git rev-parse HEAD) \
	  -t $(IMAGE):$(TAG) .
