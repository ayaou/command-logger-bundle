.DEFAULT_GOAL := help
START_TIME=$(time())
PERL=/usr/bin/perl
APP_ENV ?= dev

# Detect docker compose command
DOCKER_COMPOSE := $(shell command -v docker-compose 2>/dev/null || command -v docker compose 2>/dev/null)
ifeq ($(DOCKER_COMPOSE),)
	$(error "Neither 'docker-compose' nor 'docker compose' found, please install Docker Compose")
endif



PHP_FPM_SERVICE ?= "app"

# Composer
COMPOSER_ROOT ?= /opt/app-root/src
PROJECT_ROOT ?= /opt/app-root/src
PROJECT_NAME ?= command_logger_bundle
COMPOSE_FILE_PATH ?= -f .docker/docker-compose.yml

# PHP Unit
PHPUNIT_EXEC ?= $(PROJECT_ROOT)/vendor/bin/phpunit
PHPUNIT_CONF ?= $(PROJECT_ROOT)/phpunit.xml
PHPUNIT_DEFAULT_TESTSUITE ?= "default"
PHPUNIT_UNIT_TESTSUITE ?= "unit"
PHPUNIT_APPLICATION_TESTSUITE ?= "application"
PHPUNIT_INTEGRATION_TESTSUITE ?= "integration"

# Symfony
SYMFONY_USER ?= www-data

# PHP Code Standards Fixer
PHPCSF_EXEC ?= $(PROJECT_ROOT)/vendor/bin/php-cs-fixer

# PHP CodeSniffer
PHPCS_EXTENSIONS ?= php,module,inc,install,test,profile,theme,css,info,txt,md,yml

# PHPStan
PHPSTAN_EXEC ?= $(PROJECT_ROOT)/vendor/bin/phpstan
PHPSTAN_EXEC_CONFIG ?= $(PROJECT_ROOT)/phpstan.neon

# Rector
RECTOR_EXEC ?= $(PROJECT_ROOT)/vendor/bin/rector
RECTOR_FOLDER ?= $(PROJECT_ROOT)/src

HELP_FUN = \
%help; while(<>){push@{$$help{$$2//'options'}},[$$1,$$3] \
if/^([\w\-]+)\s*:.*\#\#(?:@([^@]*)@)?\s(.*)$$/}; \
print"$$_:\n", map" $$_->[0]".(" "x(32-length($$_->[0])))."$$_->[1]\n",\
@{$$help{$$_}},"\n" for sort keys %help

help: ##@Miscellaneous@ Show this help
	@echo "Usage: make [target] ...\n"
	@echo "Project name: $(PROJECT_NAME) \n"
	@$(PERL) -e '$(HELP_FUN)' $(MAKEFILE_LIST)

.PHONY: build
build: ##@Docker@ Builds current project image.
	@echo "Build Docker image."
	$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) build \
		$(filter-out $@,$(MAKECMDGOALS))

.PHONY: up
up: ##@Docker@ Start up containers.
	@echo "Starting up containers."
	$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) up -d --force-recreate --remove-orphans

.PHONY: pull-build
pull-build: ##@Docker@ Pull and build then Start up containers.
	@echo "Pull, build and start up containers."
	$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH)
	make build " --pull --no-cache"
	$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) up -d --remove-orphans

.PHONY: down
down: ##@Docker@ Stop containers.
	@echo "Stop containers and remove containers, networks, volumes, and images created."
	@$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) down

.PHONY: restart
restart: ##@Docker@ Restart containers.
	make down
	make up

.PHONY: bash
bash: ##@Docker@ Get interactive prompt into web container.
	@$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) exec $(PHP_FPM_SERVICE) bash

.PHONY: fix-permissions
fix-permissions: ##@Docker@ Fix permissions to www-data user.
	@echo "@$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) exec $(PHP_FPM_SERVICE) chown -R $(SYMFONY_USER):$(SYMFONY_USER) $(PROJECT_ROOT)"

.PHONY: composer
composer: ##@Composer@ Executes composer command with arguments.
	make fix-permissions
	@$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) exec $(PHP_FPM_SERVICE) composer $(filter-out $@,$(MAKECMDGOALS))
	make fix-permissions

.PHONY: phpcsf
phpcsf: ##@Tests And Code Quality@ Run PHP Coding Standards Fixer on a specified path
	@$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) exec $(PHP_FPM_SERVICE) $(PHPCSF_EXEC) fix -v
	make fix-permissions

.PHONY: phpcsf-dry
phpcsf-dry: ##@Tests And Code Quality@ Run PHP Coding Standards Fixer on a specified path with Dry mode
	@$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) exec $(PHP_FPM_SERVICE) $(PHPCSF_EXEC) --dry-run fix -v

.PHONY: phpstan
phpstan: ##@Tests And Code Quality@ Run PHP stan analysis on a specific folder with a certain level
	@$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) exec $(PHP_FPM_SERVICE) $(PHPSTAN_EXEC) analyse -c $(PHPSTAN_EXEC_CONFIG) $(filter-out $@,$(MAKECMDGOALS))

.PHONY: rector-dry
rector-dry: ##@Tests And Code Quality@ Run rector on dry mode command on specific folder (src by default)
	@$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) exec $(PHP_FPM_SERVICE) $(RECTOR_EXEC) process $(RECTOR_FOLDER) --dry-run $(filter-out $@,$(MAKECMDGOALS))

.PHONY: rector
rector: ##@Tests And Code Quality@ Run rector command on specific folder (src by default)
	@$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) exec $(PHP_FPM_SERVICE) $(RECTOR_EXEC) process $(RECTOR_FOLDER) $(filter-out $@,$(MAKECMDGOALS))
	make fix-permissions

.PHONY: code-quality
code-quality: ##@Run all quality jobs@
	make phpcsf-dry
	make phpstan
	make rector-dry

.PHONY: check-quality
check-quality: ##@Tests And Code Quality@ Run all quality and tests jobs
	@date +%s > /tmp/Makefile_time_txt
	@echo "---------------------------- Check quality starts ----------------------------"
	make composer " install --no-dev --no-scripts"
	make console  " c:c --env=prod --no-debug"
	make composer " install"
	make console  " c:c --env=dev"
	make console  " lint:container"
	make console  " lint:twig templates"
	make console  " lint:yaml config"
	make phpcsf-dry
	make phpstan
	make rector-dry
	make run-tests
	@echo "Script took: "$$(($$(date +%s)-$$(cat /tmp/Makefile_time_txt))) "Seconds"
	@echo "---------------------------- Check quality Good ----------------------------"

.PHONY: run-tests
run-tests: ##@Tests And Code Quality@ Run all tests
	@$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) exec $(PHP_FPM_SERVICE) runuser -u $(SYMFONY_USER) -- $(PHPUNIT_EXEC) -c $(PHPUNIT_CONF) --stop-on-failure -v --debug --testsuite $(PHPUNIT_DEFAULT_TESTSUITE)
	make fix-permissions

.PHONY: clear
clear: ##@Symfony@ Executes clear cache command.
	@$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) exec $(PHP_FPM_SERVICE) runuser -u $(SYMFONY_USER) -- console cache:clear $(filter-out $@,$(MAKECMDGOALS))
	make fix-permissions

.PHONY: console
console: ##@Symfony@ Executes symfony console command with arguments.
	@$(DOCKER_COMPOSE) -p $(PROJECT_NAME) $(COMPOSE_FILE_PATH) exec $(PHP_FPM_SERVICE) runuser -u $(SYMFONY_USER) -- console $(filter-out $@,$(MAKECMDGOALS))
	make fix-permissions


.PHONY: reset-db
reset-db: ##@Database@ drop, create and migrate database
	@echo "⚠️  WARNING: This will permanently delete and recreate the database! ⚠️"
	@read -p "Are you sure you want to continue? (yes/no): " CONFIRM && [ "$$CONFIRM" = "yes" ] || (echo "Aborted."; exit 1)
	make console " doctrine:database:drop --force"
	make console " doctrine:database:create"
	make console " doctrine:migrations:migrate --no-interaction"
	make console " app:airports:sync var/data/airports.csv"
%:
	@: # No-op
