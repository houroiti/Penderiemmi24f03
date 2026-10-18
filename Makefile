# Penderie : commandes du projet
# Usage : make <cible>   (make help pour la liste)

DC      = docker compose
PHP     = $(DC) exec php
CONSOLE = $(PHP) bin/console

.DEFAULT_GOAL := help
.PHONY: help up down sh phpstan lint lint-container lint-yaml lint-twig audit validate quality test test-db fixtures

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

up: ## Démarre les conteneurs
	$(DC) up -d

down: ## Arrête les conteneurs
	$(DC) down

sh: ## Ouvre un terminal dans le conteneur PHP
	$(PHP) sh

phpstan: ## Analyse statique PHPStan (niveau 6)
	$(CONSOLE) cache:warmup --quiet
	$(PHP) vendor/bin/phpstan analyse --memory-limit=512M

lint: lint-container lint-yaml lint-twig ## Lance tous les linters Symfony

lint-container: ## Vérifie la configuration des services
	$(CONSOLE) lint:container

lint-yaml: ## Vérifie la syntaxe des fichiers YAML
	$(CONSOLE) lint:yaml config --parse-tags

lint-twig: ## Vérifie la syntaxe des templates Twig
	$(CONSOLE) lint:twig templates

audit: ## Cherche les failles connues dans les dépendances
	$(PHP) composer audit

validate: ## Vérifie que composer.json est valide
	$(PHP) composer validate --strict

quality: validate lint phpstan audit ## Lance tous les contrôles qualité

test-db: ## (Re)crée la base de test et joue les migrations
	$(CONSOLE) doctrine:database:drop --env=test --force --if-exists
	$(CONSOLE) doctrine:database:create --env=test
	$(CONSOLE) doctrine:migrations:migrate --env=test --no-interaction

test: ## Lance les tests PHPUnit
	$(PHP) bin/phpunit

fixtures: ## Vide la base de dev et charge les données de démo
	$(CONSOLE) doctrine:fixtures:load --no-interaction
