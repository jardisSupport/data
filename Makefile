SHELL := bash
.SHELLFLAGS := -eu -o pipefail -c
MAKEFLAGS += --warn-undefined-variables
DOCKER_COMPOSE := docker compose

include .env

help:
	@echo -e "\033[0;32m Usage: make [target] "
	@echo
	@echo -e "\033[1m targets:\033[0m"
	@egrep '^(.+):*\ ##\ (.+)' ${MAKEFILE_LIST} | column -t -c 2 -s ':#'
.PHONY: help

<---composer----->: ## -----------------------------------------------------------------------
install: ## Run composer install
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli composer install --no-cache
.PHONY: install

update: ## Run composer update
	$(DOCKER_COMPOSE) run --rm --no-deps -e XDEBUG_MODE=off phpcli composer update
.PHONY: update

autoload: ## Run composer dump-autoload
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli composer dumpautoload
.PHONY: autoload

<---qa tools----->: ## -----------------------------------------------------------------------
phpunit: ## Run all tests
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli vendor/bin/phpunit
.PHONY: phpunit

phpunit-reports: ## Run all tests with reports
	$(DOCKER_COMPOSE) run --rm --no-deps -e PCOV_ENABLED=1 phpcli vendor/bin/phpunit --bootstrap ./tests/bootstrap.php /app/tests --coverage-clover tests/reports/clover.xml --coverage-xml tests/reports/coverage-xml
.PHONY: phpunit-reports

phpunit-coverage: ## Run all tests with coverage text
	$(DOCKER_COMPOSE) run --rm --no-deps -e PCOV_ENABLED=1 phpcli vendor/bin/phpunit --bootstrap ./tests/bootstrap.php /app/tests --coverage-text
.PHONY: phpunit-coverage

phpunit-coverage-html: ## Run all tests with HTML coverage
	$(DOCKER_COMPOSE) run --rm --no-deps -e PCOV_ENABLED=1 phpcli vendor/bin/phpunit --bootstrap ./tests/bootstrap.php /app/tests --coverage-html tests/reports/coverage-html
.PHONY: phpunit-coverage-html

phpstan: ## Run PHPStan analysis
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli vendor/bin/phpstan analyse /app/src -c phpstan.neon
.PHONY: phpstan

phpcs: ## Run coding standards
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli vendor/bin/phpcs /app/src
.PHONY: phpcs

<---development----->: ## -----------------------------------------------------------------------
shell: ## Run a shell inside the phpcli container
	$(DOCKER_COMPOSE) run --rm --no-deps -it phpcli sh
.PHONY: shell

<---docker----->: ## -----------------------------------------------------------------------
clean: ## Clean up containers and volumes
	@echo "Cleaning up containers and volumes..."
	@$(DOCKER_COMPOSE) down -v --remove-orphans
	@echo "Cleanup complete."
.PHONY: clean

remove: ## Remove containers, images, network and caches
	@echo "Removing all Docker resources..."
	@$(DOCKER_COMPOSE) down --volumes --remove-orphans --rmi "all"
	@docker images --filter dangling=true -q 2>/dev/null | xargs -r docker rmi 2>/dev/null || true
	@echo "Complete removal done."
.PHONY: remove
