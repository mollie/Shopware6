#
# Makefile
#
.PHONY: help prod dev clean build fixtures pr release
.DEFAULT_GOAL := help
PLUGIN_VERSION = $(shell php -r 'echo json_decode(file_get_contents("composer.json"))->version;')

NODE_VERSION:=$(shell node -v)

ifndef nossl
	EXPORT_CMD := export NODE_OPTIONS=--openssl-legacy-provider &&
else
	EXPORT_CMD :=
endif

help:
	@echo ""
	@echo "PROJECT COMMANDS"
	@echo "--------------------------------------------------------------------------------------------"
	@printf "\033[33mInstallation:%-30s\033[0m %s\n"
	@grep -E '^[a-zA-Z_-]+:.*?##1 .*$$' $(firstword $(MAKEFILE_LIST)) | awk 'BEGIN {FS = ":.*?##1 "}; {printf "\033[33m  - %-30s\033[0m %s\n", $$1, $$2}'
	@echo "--------------------------------------------------------------------------------------------"
	@printf "\033[36mDevelopment:%-30s\033[0m %s\n"
	@grep -E '^[a-zA-Z_-]+:.*?##2 .*$$' $(firstword $(MAKEFILE_LIST)) | awk 'BEGIN {FS = ":.*?##2 "}; {printf "\033[36m  - %-30s\033[0m %s\n", $$1, $$2}'
	@echo "--------------------------------------------------------------------------------------------"
	@printf "\033[32mTests:%-30s\033[0m %s\n"
	@grep -E '^[a-zA-Z_-]+:.*?##3 .*$$' $(firstword $(MAKEFILE_LIST)) | awk 'BEGIN {FS = ":.*?##3 "}; {printf "\033[32m  - %-30s\033[0m %s\n", $$1, $$2}'
	@echo "---------------------------------------------------------------------------------------------------------"
	@printf "\033[35mDevOps:%-30s\033[0m %s\n"
	@grep -E '^[a-zA-Z_-]+:.*?##4 .*$$' $(firstword $(MAKEFILE_LIST)) | awk 'BEGIN {FS = ":.*?##4 "}; {printf "\033[35m  - %-30s\033[0m %s\n", $$1, $$2}'

# ------------------------------------------------------------------------------------------------------------

prod: ##1 Installs all production dependencies
	# ----------------------------------------------------------------
	composer validate
	composer install --no-dev
	cd src/Resources/app/administration && npm install --omit=dev
	cd src/Resources/app/storefront && npm install --omit=dev

dev: ##1 Installs all dev dependencies
	composer validate
	composer install
	cd dev && npm install
	chmod a+x dev/node_modules/.bin/prettier
	cd src/Resources/app/administration && npm install
	cd src/Resources/app/storefront && npm install


clean: ##1 Cleans all dependencies and files
	rm -rf vendor/*
	# ------------------------------------------------------
	rm -rf .reports | true
	# ------------------------------------------------------
	rm -rf ./dev/node_modules/*
	rm -rf config-*
	rm -rf ./src/Resources/app/administration/node_modules/*
	rm -rf ./src/Resources/app/storefront/node_modules/*
	# ------------------------------------------------------
	rm -rf ./src/Resources/app/storefront/dist/storefront


build: ##2 Runs the Shopware theme and asset pipeline (JS assets must be built beforehand via shopware-cli).
	rm -rf ../../../public/bundles/molliepayments/administration
	cd ../../.. && php bin/console --no-debug theme:refresh
	cd ../../.. && php bin/console --no-debug theme:compile
	cd ../../.. && php bin/console --no-debug theme:refresh
	cd ../../.. && php bin/console --no-debug assets:install
	cd ../../.. && php bin/console --no-debug cache:clear

fixtures: ##2 Installs all available testing fixtures of the Mollie plugin
	cd ../../.. && php bin/console --no-debug cache:clear
	cd ../../.. && php bin/console --no-debug mollie:fixtures:load

pr: ##2 Prepares everything for a Pull Request
	# -----------------------------------------------------------------
	# FIXERS
	@make csfix mode=fix -B
	@make eslint mode=fix -B
	@make stylelint mode=fix -B
	@make prettier mode=fix -B
	# -----------------------------------------------------------------
	# VALIDATORS
	@make phpcheck -B
	@make phpmin -B
	@make stan -B
	@make phpunit -B
	@make phpintegration -B
	@make behat -B
	@make vitest -B
	@make configcheck -B
	@make phpunuhi -B

snippetexport: ##2 Exports all snippets
	php vendor/bin/phpunuhi export --configuration=./config/.phpunuhi.xml --dir=./.phpunuhi

snippetimport: ##2 Imports the provided snippet set [set=xyz file=xz.csv]
	php vendor/bin/phpunuhi import --configuration=./config/.phpunuhi.xml --set=$(set) --file=$(file) --intent=1

# ------------------------------------------------------------------------------------------------------------

phpcheck: ##3 Starts the PHP syntax checks
	@find . -name '*.php' -not -path "./vendor/*" -not -path "./tests/*" | xargs -n 1 -P4 php -l

phpmin: ##3 Starts the PHP compatibility checks
	echo "PHPCompatibility is in alpha right now and has issues with enums"
	#@php vendor/bin/phpcs -p --standard=PHPCompatibility --extensions=php --runtime-set testVersion 8.2 ./src ./shopware

csfix: ##3 Starts the PHP CS Fixer
ifndef mode
	php vendor/bin/php-cs-fixer fix --config=./config/.php_cs.php --dry-run --show-progress=dots --verbose
endif
ifeq ($(mode), fix)
	php vendor/bin/php-cs-fixer fix --config=./config/.php_cs.php --show-progress=dots --verbose
endif

stan: ##3 Starts the PHPStan Analyser
	cd ../../.. && php vendor/bin/phpstan analyse -c ./custom/plugins/MolliePayments/config/.phpstan.neon

phpunit: ##3 Starts all PHPUnit Tests
	@XDEBUG_MODE=coverage php vendor/bin/phpunit --configuration=./config/phpunit.xml --coverage-html ./.reports/phpunit/coverage

phpintegration: ##3 Starts all PHPUnit Tests
	#we call "real" phpunit, it seems like in sw 6.4 the vendor/bin/phpunit is overwritten by shopware
	@XDEBUG_MODE=coverage cd ../../.. && php vendor/phpunit/phpunit/phpunit --configuration=./custom/plugins/MolliePayments/config/phpunit.integration.xml

behat:
	cd ../../.. && php vendor/bin/behat --config ./custom/plugins/MolliePayments/config/behat.yaml

insights: ##3 Starts the PHPInsights Analyser
	@php vendor/bin/phpinsights analyse --no-interaction

vitest: ##3 Starts all Vitest tests
	NODE_PATH=$(CURDIR)/dev/node_modules ./dev/node_modules/.bin/vitest -c ./config/vitest.config.ts

eslint: ##3 Starts the ESLinter
ifndef mode
	NODE_PATH=$(CURDIR)/dev/node_modules ./dev/node_modules/.bin/eslint --config ./config/.eslintrc.json ./src/Resources/app
endif
ifeq ($(mode), fix)
	NODE_PATH=$(CURDIR)/dev/node_modules ./dev/node_modules/.bin/eslint --config ./config/.eslintrc.json ./src/Resources/app --fix
endif

stylelint: ##3 Starts the Stylelinter
ifndef mode
	NODE_PATH=$(CURDIR)/dev/node_modules ./dev/node_modules/.bin/stylelint --allow-empty-input ./src/Resources/app/**/*.scss --config=./config/.stylelintrc
endif
ifeq ($(mode), fix)
	NODE_PATH=$(CURDIR)/dev/node_modules ./dev/node_modules/.bin/stylelint --allow-empty-input ./src/Resources/app/**/*.scss --fix --config=./config/.stylelintrc
endif

prettier: ##3 Starts the Prettier
ifndef mode
	./dev/node_modules/.bin/prettier ./src/Resources/app/ --config=./config/.prettierrc  --check
endif
ifeq ($(mode), fix)
	./dev/node_modules/.bin/prettier ./src/Resources/app/ --config=./config/.prettierrc  --write
endif

configcheck: ##3 Tests and verifies the plugin configuration file
	cd ./tests/Custom && php verify-plugin-config.php

phpunuhi: ##3 Tests and verifies all plugin snippets
	php vendor/bin/phpunuhi validate --configuration=./config/.phpunuhi.xml --report-format=junit --report-output=./.phpunuhi/junit.xml

# -------------------------------------------------------------------------------------------------

release: ##4 Builds a PROD version and creates a ZIP file in plugins/.build.
	cd .. && rm -rf ./.build/MolliePayments* && mkdir -p ./.build
	docker run --rm \
		-v "$(CURDIR)/..":/plugins \
		-v "$(CURDIR)/config/.shopware-extension.yml":/plugins/MolliePayments/.shopware-extension.yml \
		-w /plugins/.build \
		ghcr.io/shopware/shopware-cli:latest \
		extension zip /plugins/MolliePayments --disable-git
	@echo ""
	@echo "CONGRATULATIONS"
	@echo "ZIP file available at plugins/.build/"
