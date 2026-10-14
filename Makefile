export UID := $(shell id -u)
export GID := $(shell id -g)

DC = docker compose
ART = $(DC) exec app php artisan

.PHONY: up up-all down logs sh migrate fresh test lint

up:            ## базовые сервисы
	$(DC) up -d --build

up-all:        ## плюс horizon и scheduler (после установки Horizon)
	$(DC) --profile workers up -d --build

down:
	$(DC) --profile workers down

logs:
	$(DC) logs -f --tail=100

sh:
	$(DC) exec app sh

migrate:
	$(ART) migrate

fresh:
	$(ART) migrate:fresh --seed

test:
	$(ART) test

lint:
	$(DC) exec app vendor/bin/pint --test
	$(DC) exec app vendor/bin/phpstan analyse --memory-limit=1G
