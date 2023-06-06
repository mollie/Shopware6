#
# Makefile
#

.PHONY: help
.DEFAULT_GOAL := help

PLUGIN_VERSION=`php -r 'echo json_decode(file_get_contents("MolliePayments/composer.json"))->version;'`

SW_CLI_VERSION:=$(shell shopware-cli --version 1>/dev/null)
NODE_VERSION:=$(shell node -v)


help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ------------------------------------------------------------------------------------------------------------

prod: ## Installs all production dependencies
	# do not switch to production composer PROD, otherwise it would
	# also install shopware in here -> we just need it for the release composer.json file
	# so just switch to our dev dependency variant
	php switch-composer.php dev
	# ----------------------------------------------------------------
	@composer validate
	@composer install --no-dev
	cd src/Resources/app/administration && npm install --omit=dev
	cd src/Resources/app/storefront && npm install --production

dev: ## Installs all dev dependencies
	curl -1sLf 'https://dl.cloudsmith.io/public/friendsofshopware/stable/setup.deb.sh' | sudo -E bash && sudo apt install shopware-cli
	php switch-composer.php dev
	@composer validate
	@composer install
	cd src/Resources/app/administration && npm install
	cd src/Resources/app/storefront && npm install

install: ## [deprecated] Installs all production dependencies. Please use "make prod" now.
	@make prod -B

clean: ## Cleans all dependencies and files
	rm -rf vendor/*
	# ------------------------------------------------------
	rm -rf .reports | true
	# ------------------------------------------------------
	rm -rf ./src/Resources/app/administration/node_modules/*
	rm -rf ./src/Resources/app/storefront/node_modules/*
	# ------------------------------------------------------
	rm -rf ./src/Resources/app/storefront/dist/storefront
	rm -rf ./src/Resources/public

build: ## Installs the plugin, and builds the artifacts using the Shopware build commands.
	php switch-composer.php prod
	cd ../../.. && export NODE_OPTIONS=openssl-legacy-provider && shopware-cli extension build custom/plugins/MolliePayments
	php switch-composer.php dev
	# -----------------------------------------------------
	# CUSTOM WEBPACK
	cd ./src/Resources/app/storefront && make build -B
	# -----------------------------------------------------
	cd ../../.. && php bin/console theme:refresh
	cd ../../.. && php bin/console theme:compile
	cd ../../.. && php bin/console theme:refresh
	cd ../../.. && php bin/console assets:install
	cd ../../.. && php bin/console cache:clear

fixtures: ## Installs all available testing fixtures of the Mollie plugin
	cd ../../.. && php bin/console cache:clear
	cd ../../.. && php bin/console fixture:load:group mollie

# ------------------------------------------------------------------------------------------------------------

phpcheck: ## Starts the PHP syntax checks
	@find . -name '*.php' -not -path "./vendor/*" -not -path "./tests/*" | xargs -n 1 -P4 php -l

phpmin: ## Starts the PHP compatibility checks
	@php vendor/bin/phpcs -p --standard=PHPCompatibility --extensions=php --runtime-set testVersion 7.2 ./src

csfix: ## Starts the PHP CS Fixer
	@PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=./.php_cs.php --dry-run

stan: ## Starts the PHPStan Analyser
	@php vendor/bin/phpstan analyse -c ./.phpstan.neon

phpunit: ## Starts all PHPUnit Tests
	@XDEBUG_MODE=coverage php vendor/bin/phpunit --configuration=phpunit.xml --coverage-html ./.reports/phpunit/coverage

infection: ## Starts all Infection/Mutation tests
	@XDEBUG_MODE=coverage php vendor/bin/infection --configuration=./.infection.json --log-verbosity=all --debug

insights: ## Starts the PHPInsights Analyser
	@php vendor/bin/phpinsights analyse --no-interaction

jest: ## Starts all Jest tests
	cd ./src/Resources/app/administration && ./node_modules/.bin/jest --config=.jest.config.js --coverage
	cd ./src/Resources/app/storefront && ./node_modules/.bin/jest --config=.jest.config.js --coverage

stryker: ## Starts the Stryker Jest Mutation Tests
	cd ./src/Resources/app/administration && ./node_modules/.bin/stryker run .stryker.conf.json
	@# Storefront has no tests at the moment
	@# cd ./src/Resources/app/storefront && ./node_modules/.bin/stryker run .stryker.conf.json

eslint: ## Starts the ESLinter
	cd ./src/Resources/app/administration && ./node_modules/.bin/eslint --config ./.eslintrc.json ./src
	cd ./src/Resources/app/storefront && ./node_modules/.bin/eslint --config ./.eslintrc.json ./src

stylelint: ## Starts the Stylelinter
	cd ./src/Resources/app/administration && ./node_modules/.bin/stylelint --allow-empty-input ./src/**/*.scss
	cd ./src/Resources/app/storefront && ./node_modules/.bin/stylelint --allow-empty-input ./src/**/*.scss

configcheck: ## Tests and verifies the plugin configuration file
	cd ./tests/Custom && php verify-plugin-config.php

# ------------------------------------------------------------------------------------------------------------

snippetcheck: ## Tests and verifies all plugin snippets
	php vendor/bin/phpunuhi validate --configuration=./.phpunuhi.xml --report-format=junit --report-output=./.phpunuhi/junit.xml

snippetexport: ## Exports all snippets
	php vendor/bin/phpunuhi export --configuration=./.phpunuhi.xml --dir=./.phpunuhi

snippetimport: ## Imports the provided snippet set [set=xyz file=xz.csv]
	php vendor/bin/phpunuhi import --configuration=./.phpunuhi.xml --set=$(set) --file=$(file) --intent=1

# ------------------------------------------------------------------------------------------------------------

pr: ## Prepares everything for a Pull Request
	@PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=./.php_cs.php
	@make phpcheck -B
	@make phpmin -B
	@make stan -B
	@make phpunit -B
	@make infection -B
	@make jest -B
	@make stryker -B
	@make eslint -B
	@make stylelint -B
	@make configcheck -B
	@make snippetcheck -B

# -------------------------------------------------------------------------------------------------

release: ## Builds a PROD version and creates a ZIP file in plugins/.build.
ifneq (,$(findstring v12,$(NODE_VERSION)))
	$(warning Attention, reqruires Node v14 or higher to build a release!)
	@exit 1
endif
	cd .. && rm -rf ./.build/MolliePayments* && mkdir -p ./.build
	# -------------------------------------------------------------------------------------------------
	@echo "UPDATE SHOPWARE DEPENDENCIES"
	php switch-composer.php dev
	composer update shopware/core
	composer update shopware/storefront
	composer update shopware/administration
	# -------------------------------------------------------------------------------------------------
	@echo "INSTALL DEV DEPENDENCIES AND BUILD"
	make clean -B
	make dev -B
	make build -B
	# -------------------------------------------------------------------------------------------------
	@echo "INSTALL PRODUCTION DEPENDENCIES"
	make prod -B
	rm -rf ./src/Resources/app/storefront/node_modules/*
	# DELETE distribution file. that ones not compatible between 6.5 and 6.4
	# if one wants to use it, they need to run build-storefront.sh manually and activate that feature
	# in our plugin configuration! (use shopware standard js)
	rm -rf ./src/Resources/app/storefront/dist/storefront
	# switch to PROD dependencies before zipping plugin
	# this is very important for the Shopware Store.
	php switch-composer.php prod
	# -------------------------------------------------------------------------------------------------
	@echo "CREATE ZIP FILE"
	cd .. && zip -qq -r -0 ./.build/MolliePayments.zip MolliePayments/ -x '*.editorconfig' '*.git*' '*.reports*' '*/.idea*' '*/tests*' '*/node_modules*' '*/makefile' '*.DS_Store' '*/.shopware-extension.yml' '*/switch-composer.php' '*/phpunit.xml' '*/.phpunuhi.xml' '*/.infection.json' '*/phpunit.autoload.php' '*/.phpstan*' '*/.php_cs.php' '*/phpinsights.php'
	# -------------------------------------------------------------------------------------------------
	@echo "RESET COMPOSER.JSON"
	php switch-composer.php dev
	# -------------------------------------------------------------------------------------------------
	@echo ""
	@echo "CONGRATULATIONS"
	@echo "The new ZIP file is available at plugins/.build/MolliePayments.zip"
