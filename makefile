#
# Makefile
#

.PHONY: help
.DEFAULT_GOAL := help

PLUGIN_VERSION=`php -r 'echo json_decode(file_get_contents("MolliePayments/composer.json"))->version;'`

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ------------------------------------------------------------------------------------------------------------

install: ## Installs all production dependencies
	# do not switch to production composer PROD, otherwise it would
	# also install shopware in here -> we just need it for the release composer.json file
	# so just switch to our dev dependency variant
	php switch-composer.php dev
	# ----------------------------------------------------------------
	@composer validate
	@composer install --no-dev
	cd src/Resources/app/administration && npm install --production
	cd src/Resources/app/storefront && npm install --production

dev: ## Installs all dev dependencies
	php switch-composer.php dev
	@composer validate
	@composer install
	cd src/Resources/app/administration && npm install
	cd src/Resources/app/storefront && npm install

clean: ## Cleans all dependencies
	rm -rf vendor/*
	rm -rf .reports | true
	rm -rf ./src/Resources/app/administration/node_modules/*
	rm -rf ./src/Resources/app/storefront/node_modules/*

fixtures: ## Installs all available testing fixtures of the Mollie plugin
	cd /var/www/html && php bin/console cache:clear
	cd /var/www/html && php bin/console fixture:load

build: ## Installs the plugin, and builds the artifacts using the Shopware build commands (requires Shopware)
	cd /var/www/html && php bin/console plugin:refresh
	cd /var/www/html && php bin/console plugin:install MolliePayments --activate | true
	cd /var/www/html && php bin/console plugin:refresh
	cd /var/www/html && php bin/console theme:dump
	cd /var/www/html && PUPPETEER_SKIP_DOWNLOAD=1 ./bin/build-js.sh
	cd /var/www/html && php bin/console theme:refresh
	cd /var/www/html && php bin/console theme:compile
	cd /var/www/html && php bin/console theme:refresh

# ------------------------------------------------------------------------------------------------------------

phpcheck: ## Starts the PHP syntax checks
	@find . -name '*.php' -not -path "./vendor/*" -not -path "./tests/*" | xargs -n 1 -P4 php -l

phpmin: ## Starts the PHP compatibility checks
	@php vendor/bin/phpcs -p --standard=PHPCompatibility --extensions=php --runtime-set testVersion 7.2 ./src

csfix: ## Starts the PHP CS Fixer
	@php vendor/bin/php-cs-fixer fix --config=./.php_cs.php --dry-run

stan: ## Starts the PHPStan Analyser
	@php vendor/bin/phpstan analyse -c ./.phpstan.neon
	@php vendor/bin/phpstan analyse -c ./.phpstan.lvl8.neon

phpunit: ## Starts all PHPUnit Tests
	@XDEBUG_MODE=coverage php vendor/bin/phpunit --configuration=phpunit.xml --coverage-html ../../../public/.reports/mollie/coverage

infection: ## Starts all Infection/Mutation tests
	@XDEBUG_MODE=coverage php vendor/bin/infection --configuration=./.infection.json --log-verbosity=all --debug

insights: ## Starts the PHPInsights Analyser
	@php vendor/bin/phpinsights analyse --no-interaction

jest: ## Starts all Jest tests
	cd ./src/Resources/app/administration && ./node_modules/.bin/jest --config=.jest.config.js
	cd ./src/Resources/app/storefront && ./node_modules/.bin/jest --config=.jest.config.js

eslint: ## Starts the ESLinter
	cd ./src/Resources/app/administration && ./node_modules/.bin/eslint --config ./.eslintrc.json ./src
	cd ./src/Resources/app/storefront && ./node_modules/.bin/eslint --config ./.eslintrc.json ./src

stylelint: ## Starts the Stylelinter
	cd ./src/Resources/app/administration && ./node_modules/.bin/stylelint --allow-empty-input ./src/**/*.scss
	cd ./src/Resources/app/storefront && ./node_modules/.bin/stylelint --allow-empty-input ./src/**/*.scss

# ------------------------------------------------------------------------------------------------------------

pr: ## Prepares everything for a Pull Request
	@php vendor/bin/php-cs-fixer fix --config=./.php_cs.php
	@make phpcheck -B
	@make phpmin -B
	@make stan -B
	@make phpunit -B
	@make infection -B
	@make jest -B
	@make eslint -B
	@make stylelint -B

release: ## Builds a PROD version and creates a ZIP file
	make clean -B
	make install -B
	make build -B
	php switch-composer.php prod
	cd .. && rm -rf MolliePayments-$(PLUGIN_VERSION).zip
	cd .. && zip -qq -r -0 MolliePayments-$(PLUGIN_VERSION).zip MolliePayments/ -x '*.editorconfig' '*.git*' '*.reports*' '*/.idea*' '*/tests*' '*/node_modules*' '*/makefile' '*.DS_Store' '*/switch-composer.php' '*/phpunit.xml' '*/.infection.json' '*/phpunit.autoload.php' '*/.phpstan*' '*/.php_cs.php' '*/phpinsights.php'
	php switch-composer.php dev
