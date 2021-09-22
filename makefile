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
	@composer install --no-dev
	cd src/Resources/app/administration && npm install --production
	cd src/Resources/app/storefront && npm install --production

dev: ## Installs all dev dependencies
	@composer install
	cd src/Resources/app/administration && npm install
	cd src/Resources/app/storefront && npm install

clean: ## Cleans all dependencies
	rm -rf vendor
	rm -rf .reports | true
	rm -rf ./src/Resources/app/administration/node_modules
	rm -rf ./src/Resources/app/administration/package-lock.json
	rm -rf ./src/Resources/app/storefront/node_modules
	rm -rf ./src/Resources/app/storefront/package-lock.json

build: ## Builds the artifacts using the Shopware build commands (requires Shopware)
	cd /var/www/html && php bin/console theme:dump
	cd /var/www/html && ./bin/build-administration.sh
	cd /var/www/html && ./bin/build-storefront.sh

# ------------------------------------------------------------------------------------------------------------

phpunit: ## Starts all PHPUnit Tests
	@XDEBUG_MODE=coverage php vendor/bin/phpunit --configuration=phpunit.xml --coverage-html ../../../public/.reports/mollie/coverage

phpcheck: ## Starts the PHP syntax checks
	@find . -name '*.php' -not -path "./vendor/*" -not -path "./tests/*" | xargs -n 1 -P4 php -l

phpmin: ## Starts the PHP compatibility checks
	@php vendor/bin/phpcs -p --standard=PHPCompatibility --extensions=php --runtime-set testVersion 7.2 ./src

csfix: ## Starts the PHP CS Fixer
	@php vendor/bin/php-cs-fixer fix --config=./.php_cs.php --dry-run

stan: ## Starts the PHPStan Analyser
	@php vendor/bin/phpstan analyse -c ./.phpstan.neon
	@php vendor/bin/phpstan analyse -c ./.phpstan.lvl8.neon

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
	cd ./src/Resources/app/storefront && ./node_modules/.bin/stylelint ./src/scss/**/*.scss

# ------------------------------------------------------------------------------------------------------------

pr: ## Prepares everything for a Pull Request
	@php vendor/bin/php-cs-fixer fix --config=./.php_cs.php
	@make phpcheck -B
	@make phpmin -B
	@make stan -B
	@make phpunit -B
	@make jest -B
	@make eslint -B
	@make stylelint -B

release: ## Creates a new ZIP package
	@cd .. && rm -rf MolliePayments-$(PLUGIN_VERSION).zip
	@cd .. && zip -qq -r -0 MolliePayments-$(PLUGIN_VERSION).zip MolliePayments/ -x '.editorconfig' '*.git*' '*.reports*' '*/tests*' '*/node_modules*' '*/makefile' '*.DS_Store' '*/phpunit.xml' '*/.phpstan.neon' '*/.php_cs.php' '*/phpinsights.php'
