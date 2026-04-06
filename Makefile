.PHONY: help up down clean logs ps build install sh

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

down: ## Stop all services (dev/prod) and keep data
	$(DOCKER_COMPOSE) down

clean: ## Stop and remove containers, networks, and volumes
	$(DOCKER_COMPOSE) down -v --remove-orphans

logs: ## Tail logs and follow log output
	$(DOCKER_COMPOSE) logs -f

ps: ## List running containers for this project
	$(DOCKER_COMPOSE) ps

##@ Build images

build: ## Build image
	$(DOCKER_COMPOSE) build

##@ Development Workflow

install: ## Install PHP dependencies in dev container
	$(DOCKER_COMPOSE) run --rm app composer install

ci: ## Run full CI checks (lint, CS, PHPStan, tests) in dev container
	$(DOCKER_COMPOSE) run --rm app composer ci

sh: ## Open an interactive shell in dev container
	-$(DOCKER_COMPOSE) exec app sh || $(DOCKER_COMPOSE) run --rm -it app sh

