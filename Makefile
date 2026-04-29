.PHONY: help up down clean logs ps build build-prod install ci sh adoc puml yaml split-json publish-all

# Default target
.DEFAULT_GOAL := help

# Colors for output
CYAN := \033[0;36m
GREEN := \033[0;32m
YELLOW := \033[0;33m
RED := \033[0;31m
NC := \033[0m # No Color

# Configuration: Docker files live in .docker/; run from repo root
DOCKER_COMPOSE ?= docker compose -f .docker/docker-compose.yml

##@ General

help: ## Display this help message
	@echo ""
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make $(CYAN)<target>$(NC)\n"} /^[a-zA-Z_0-9-]+:.*?##/ { printf "  $(CYAN)%-20s$(NC) %s\n", $$1, $$2 } /^##@/ { printf "\n$(YELLOW)%s$(NC)\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Container Management

up: ## Start dev containers in background
	$(DOCKER_COMPOSE) up -d --force-recreate

down: ## Stop all services and keep data
	$(DOCKER_COMPOSE) down

clean: ## Stop and remove containers, networks, and volumes
	$(DOCKER_COMPOSE) down -v --remove-orphans

logs: ## Tail logs and follow log output
	$(DOCKER_COMPOSE) logs -f

ps: ## List running containers for this project
	$(DOCKER_COMPOSE) ps

##@ Build images

build: ## Build development image (with xdebug, Composer)
	$(DOCKER_COMPOSE) build

build-prod: ## Build production image (no xdebug, no Composer)
	docker build -f .docker/Dockerfile --target production -t bmm-publisher .

##@ Development Workflow

install: ## Install PHP dependencies in dev container
	$(DOCKER_COMPOSE) run --rm app composer install

ci: ## Run full CI checks (lint, CS, PHPStan, tests) in dev container
	$(DOCKER_COMPOSE) run --rm app composer ci

sh: ## Open an interactive shell in dev container
	-$(DOCKER_COMPOSE) exec app sh || $(DOCKER_COMPOSE) run --rm -it app sh

##@ Publishing
#
# Each schema is loaded with its dependencies so cross-schema type
# references resolve correctly (e.g. RM needs BASE, AM needs BASE+LANG).
#
PUBLISH_RUNS = \
	./bin/bmm-publisher $(1) openehr_am_2.3.0 openehr_lang_1.0.0 openehr_base_1.2.0 \
	&& ./bin/bmm-publisher $(1) openehr_am_2.4.0 openehr_lang_1.1.0 openehr_base_1.3.0 \
	&& ./bin/bmm-publisher $(1) openehr_rm_1.0.4 openehr_base_1.1.0 \
	&& ./bin/bmm-publisher $(1) openehr_rm_1.1.0 openehr_base_1.2.0 \
	&& ./bin/bmm-publisher $(1) openehr_rm_1.2.0 openehr_base_1.3.0 \
	&& ./bin/bmm-publisher $(1) openehr_am_1.4.0 openehr_base_1.1.0 \
	&& ./bin/bmm-publisher $(1) openehr_am_2.2.0 openehr_base_1.1.0 \
	&& ./bin/bmm-publisher $(1) openehr_lang_1.1.0 openehr_base_1.3.0 \
	&& ./bin/bmm-publisher $(1) openehr_lang_1.0.0 \
	&& ./bin/bmm-publisher $(1) openehr_base_1.1.0 \
	&& ./bin/bmm-publisher $(1) openehr_base_1.2.0 \
	&& ./bin/bmm-publisher $(1) openehr_base_1.3.0 \
	&& ./bin/bmm-publisher $(1) openehr_term_3.0.0 \
	&& ./bin/bmm-publisher $(1) openehr_term_3.1.0

adoc: ## Generate AsciiDoc for all schema combinations (writer + PlantUML render + SVG inline, atomic per call)
	$(DOCKER_COMPOSE) run --rm app sh -c '$(call PUBLISH_RUNS,adoc)'

puml: ## Generate standalone PlantUML tree (output/PlantUML/<schema>/...) for all schema combinations
	$(DOCKER_COMPOSE) run --rm app sh -c '$(call PUBLISH_RUNS,puml)'

yaml: ## Generate YAML for all schemas
	$(DOCKER_COMPOSE) run --rm app ./bin/bmm-publisher yaml all

split-json: ## Generate per-type split JSON
	$(DOCKER_COMPOSE) run --rm app ./bin/bmm-publisher split-json

publish-all: adoc puml yaml split-json ## Run all publishers
