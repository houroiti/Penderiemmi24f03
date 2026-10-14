# Penderie : commandes du projet
# Usage : make <cible>   (make help pour la liste)

DC      = docker compose
PHP     = $(DC) exec php
CONSOLE = $(PHP) bin/console

.DEFAULT_GOAL := help
.PHONY: help up down sh phpstan lint lint-container lint-yaml lint-twig audit validate quality

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