SHELL := /bin/sh

SERVICE ?= backend
COMMAND ?=

.PHONY: init up down restart test lint logs shell migrate

init:
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
		password=$$(docker run --rm php:8.4.25-cli-bookworm php -r 'echo bin2hex(random_bytes(24));'); \
		runtime_password=$$(docker run --rm php:8.4.25-cli-bookworm php -r 'echo bin2hex(random_bytes(24));'); \
		key=$$(docker run --rm php:8.4.25-cli-bookworm php -r 'echo "base64:".base64_encode(random_bytes(32));'); \
		awk -v password="$$password" -v runtime_password="$$runtime_password" -v key="$$key" 'BEGIN { FS=OFS="=" } $$1=="POSTGRES_PASSWORD" { print $$1, password; next } $$1=="POSTGRES_RUNTIME_PASSWORD" { print $$1, runtime_password; next } $$1=="APP_KEY" { print $$1, key; next } { print }' .env > .env.tmp; \
		mv .env.tmp .env; \
	fi
	@admin_password=$$(awk -F= '$$1=="POSTGRES_PASSWORD" {print substr($$0, index($$0, "=")+1)}' .env); \
		runtime_password=$$(awk -F= '$$1=="POSTGRES_RUNTIME_PASSWORD" {print substr($$0, index($$0, "=")+1)}' .env); \
		if [ -z "$$runtime_password" ] || [ "$$runtime_password" = "$$admin_password" ]; then \
			runtime_password=$$(docker run --rm php:8.4.25-cli-bookworm php -r 'echo bin2hex(random_bytes(24));'); \
			awk -v runtime_password="$$runtime_password" 'BEGIN { FS=OFS="=" } $$1=="POSTGRES_RUNTIME_PASSWORD" { print $$1, runtime_password; next } { print }' .env > .env.tmp; \
			mv .env.tmp .env; \
		fi
	docker compose build
	docker compose run --rm --no-deps --user root backend sh -lc 'mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache vendor && chown -R www-data:www-data storage bootstrap/cache vendor'
	docker compose run --rm --no-deps backend composer install --no-interaction --prefer-dist
	docker compose run --rm --no-deps frontend npm ci

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose down
	docker compose up -d

test:
	docker compose run --rm --no-deps \
		-e DB_CONNECTION=sqlite \
		-e DB_DATABASE=:memory: \
		-e DB_URL= \
		-e CACHE_STORE=array \
		-e QUEUE_CONNECTION=sync \
		-e SESSION_DRIVER=array \
		-e MAIL_MAILER=array \
		backend composer test
	docker compose run --rm --no-deps frontend npm test

lint:
	docker compose run --rm --no-deps backend composer lint
	docker compose run --rm --no-deps backend composer analyse
	docker compose run --rm --no-deps frontend npm run lint
	docker compose run --rm --no-deps frontend npm run typecheck

logs:
	docker compose logs --tail=200 $(SERVICE)

shell:
	@if [ -n "$(COMMAND)" ]; then docker compose exec $(SERVICE) sh -lc '$(COMMAND)'; else docker compose exec $(SERVICE) sh; fi

migrate:
	docker compose exec -T postgres bash /docker-entrypoint-initdb.d/10-runtime-role.sh
	docker compose run --rm --no-deps migration php artisan migrate --force
