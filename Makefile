# ABV-IIITM Alumni Connect - development tasks.
# Everything runs inside Docker (compose.yaml); no local PHP/MySQL needed.

DC  := docker compose
PHP := $(DC) exec -T app
NODE := docker run --rm -u $$(id -u):$$(id -g) -v "$$PWD":/app -w /app node:22-alpine

.PHONY: help install build up down restart dev dev-stop migrate fresh demo seed shell mysql logs test smoke lint assets audit

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

install: ## First-time setup: image, dependencies, key, database, assets
	@test -f .env || cp .env.example .env
	$(DC) build app
	$(DC) run --rm --no-deps app composer install
	$(DC) up -d
	$(PHP) php artisan key:generate
	$(PHP) php artisan migrate --seed --force
	$(PHP) php artisan storage:link
	$(NODE) sh -c "npm ci && npm run build"
	@rm -f public/hot
	@echo "\nReady at http://localhost:$${APP_PORT:-8090}  (mail: http://localhost:$${MAILPIT_UI_PORT:-8027})"
	@echo "Sign in as $$(grep ^SEED_ADMIN_EMAIL .env | cut -d= -f2); you will be asked to enrol in 2FA."
	@echo "Run 'make demo' for sample alumni and staff accounts.\n"

build: ## Rebuild the PHP image
	$(DC) build app

up: ## Start the stack (compiled assets)
	@rm -f public/hot
	$(DC) up -d

down: ## Stop the stack
	$(DC) down
	@rm -f public/hot

restart: down up ## Restart the stack

dev: ## Start the Vite dev server (hot reload)
	$(DC) --profile dev up -d node

dev-stop: ## Stop Vite and fall back to compiled assets
	$(DC) stop node
	@rm -f public/hot

migrate: ## Run pending migrations
	$(PHP) php artisan migrate

fresh: ## Drop everything and re-seed roles, programmes and the admin
	$(PHP) php artisan migrate:fresh --seed --force
	$(PHP) php artisan cache:clear # cached landing page and branding point at the old rows

demo: ## Load demo alumni, pending claims and staff accounts (password: Demo-Password-2026)
	$(PHP) php artisan db:seed --class=DemoDataSeeder --force

seed: ## Re-run base seeders (idempotent)
	$(PHP) php artisan db:seed --force

shell: ## Shell in the PHP container
	$(DC) exec app bash

mysql: ## MySQL client on the app database
	$(DC) exec mysql mysql -ualumni -psecret alumni_connect

logs: ## Tail app, queue and nginx logs
	$(DC) logs -f app queue nginx

test: ## PHPUnit suite (separate alumni_connect_testing database)
	$(PHP) php artisan test

smoke: ## Real-browser smoke test, incl. full admin 2FA flow (run on fresh data: make fresh demo smoke)
	node tests/Browser/smoke.mjs http://localhost:$${APP_PORT:-8090}

lint: ## Format PHP and type-check the frontend
	$(PHP) ./vendor/bin/pint
	$(NODE) npm run type-check

assets: ## Build production frontend assets
	$(NODE) npm run build
	@rm -f public/hot

audit: ## Dependency vulnerability audit (SRS 85)
	$(PHP) composer audit
	$(NODE) npm audit --omit=dev
