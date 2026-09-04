.PHONY: up down logs test lint format api-generate web-install web-build web-lint backend-validate

up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f --tail=100

test:
	docker compose run --rm app vendor/bin/pest

lint:
	docker compose run --rm app vendor/bin/pint --test
	cd web && npm run lint

format:
	docker compose run --rm app vendor/bin/pint
	cd web && npm run format

api-generate:
	./scripts/generate-api-client.sh

web-install:
	cd web && npm install

web-build:
	cd web && npm run build

web-lint:
	cd web && npm run lint

backend-validate:
	docker run --rm -v "$(CURDIR)/app:/app" -w /app composer:2 composer validate --no-check-publish
