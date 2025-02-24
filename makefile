#
# Makefile
#

.PHONY: help
.DEFAULT_GOAL := help

PLUGIN_VERSION=`php -r 'echo json_decode(file_get_contents("MolliePayments/composer.json"))->version;'`

SW_CLI_VERSION:=$(shell shopware-cli --version 1>/dev/null)
NODE_VERSION:=$(shell node -v)
SW_VERSION := 6.4.5.0
# split by dot and use 2nd word and append it to "6."
SW_MAJVER:=6.$(word 2, $(subst ., ,$(SW_VERSION)))


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
	@composer validate
	@composer install --no-dev
	npm install --omit=dev
	cd src/Resources/app/administration && npm install --omit=dev
	cd src/Resources/app/storefront && npm install --omit=dev

dev: ##1 Installs all dev dependencies
	@composer validate
    # we have to run update in dev mode, because dev dependencies are not compatible with newer php version. should be updated when support for 6.4 is dropped
	@composer update
	npm install
	cd src/Resources/app/administration && npm install
	cd src/Resources/app/storefront && npm install
	curl -1sLf 'https://dl.cloudsmith.io/public/friendsofshopware/stable/setup.deb.sh' | sudo -E bash && sudo apt install shopware-cli -y

install: ##1 [deprecated] Installs all production dependencies. Please use "make prod" now.
	@make prod -B

clean: ##1 Cleans all dependencies and files
	rm -rf vendor/*
	# ------------------------------------------------------
	rm -rf .reports | true
	# ------------------------------------------------------
	rm -rf ./node_modules/*
	rm -rf ./src/Resources/app/administration/node_modules/*
	rm -rf ./src/Resources/app/storefront/node_modules/*
	# ------------------------------------------------------
	rm -rf ./src/Resources/app/storefront/dist/storefront
	# ------------------------------------------------------
	rm -rf ./src/Resources/public/administration
	rm -rf ./src/Resources/public/mollie-payments.js

build: ##2 Installs the plugin, and builds the artifacts using the Shopware build commands.
	# CUSTOM WEBPACK
	cd ./src/Resources/app/storefront && make build -B
	cd ../../.. && export NODE_OPTIONS=--openssl-legacy-provider && shopware-cli extension build custom/plugins/MolliePayments
	# -----------------------------------------------------
	# -----------------------------------------------------
	cd ../../.. && php bin/console --no-debug theme:refresh
	cd ../../.. && php bin/console --no-debug theme:compile
	cd ../../.. && php bin/console --no-debug theme:refresh
	cd ../../.. && php bin/console --no-debug assets:install
	cd ../../.. && php bin/console --no-debug cache:clear

fixtures: ##2 Installs all available testing fixtures of the Mollie plugin
	cd ../../.. && php bin/console --no-debug cache:clear
	cd ../../.. && php bin/console --no-debug fixture:load:group mollie

pr: ##2 Prepares everything for a Pull Request
	# -----------------------------------------------------------------
	# FIXERS
	@make csfix mode=fix -B
	@make eslint mode=fix -B
	@make stylelint mode=fix -B
	# -----------------------------------------------------------------
	# VALIDATORS
	@make phpcheck -B
	@make phpmin -B
	@make stan -B
	@make phpunit -B
	@make phpintegration -B
	@make vitest -B
	@make eslint -B
	@make stylelint -B
	@make configcheck -B
	@make phpunuhi -B

snippetexport: ##2 Exports all snippets
	php vendor/bin/phpunuhi export --configuration=./.phpunuhi.xml --dir=./.phpunuhi

snippetimport: ##2 Imports the provided snippet set [set=xyz file=xz.csv]
	php vendor/bin/phpunuhi import --configuration=./.phpunuhi.xml --set=$(set) --file=$(file) --intent=1

# ------------------------------------------------------------------------------------------------------------

phpcheck: ##3 Starts the PHP syntax checks
	@find . -name '*.php' -not -path "./vendor/*" -not -path "./tests/*" | xargs -n 1 -P4 php -l

phpmin: ##3 Starts the PHP compatibility checks
	@php vendor/bin/phpcs -p --standard=PHPCompatibility --extensions=php --runtime-set testVersion 7.4 ./src

csfix: ##3 Starts the PHP CS Fixer
ifndef mode
	@PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=./.php_cs.php --dry-run
endif
ifeq ($(mode), fix)
	@PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=./.php_cs.php
endif

stan: ##3 Starts the PHPStan Analyser
	@php vendor/bin/phpstan analyse -c ./.phpstan.neon

phpunit: ##3 Starts all PHPUnit Tests
	@XDEBUG_MODE=coverage php vendor/bin/phpunit --testsuite unit --configuration=phpunit.xml --coverage-html ./.reports/phpunit/coverage

phpintegration: ##3 Starts all PHPUnit Tests
	@XDEBUG_MODE=coverage cd ../../.. && php vendor/bin/phpunit --testsuite integration --configuration=custom/plugins/MolliePayments/phpunit.xml

insights: ##3 Starts the PHPInsights Analyser
	@php vendor/bin/phpinsights analyse --no-interaction

vitest: ##3 Starts all Vitest tests
	npx vitest

eslint: ##3 Starts the ESLinter
ifndef mode
	./node_modules/.bin/eslint --config ./.eslintrc.json ./src/Resources/app
endif
ifeq ($(mode), fix)
	./node_modules/.bin/eslint --config ./.eslintrc.json ./src/Resources/app --fix
endif

stylelint: ##3 Starts the Stylelinter
ifndef mode
	./node_modules/.bin/stylelint --allow-empty-input ./src/Resources/app/**/*.scss
endif
ifeq ($(mode), fix)
	./node_modules/.bin/stylelint --allow-empty-input ./src/Resources/app/**/*.scss --fix
endif

configcheck: ##3 Tests and verifies the plugin configuration file
	cd ./tests/Custom && php verify-plugin-config.php

phpunuhi: ##3 Tests and verifies all plugin snippets
	php vendor/bin/phpunuhi validate --configuration=./.phpunuhi.xml --report-format=junit --report-output=./.phpunuhi/junit.xml

# -------------------------------------------------------------------------------------------------

release: ##4 Builds a PROD version and creates a ZIP file in plugins/.build.
ifneq (,$(findstring v12,$(NODE_VERSION)))
	$(warning Attention, reqruires Node v14 or higher to build a release!)
	@exit 1
endif
	cd .. && rm -rf ./.build/MolliePayments* && mkdir -p ./.build
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


	# -------------------------------------------------------------------------------------------------
	@echo "CREATE ZIP FILE"
	cd .. && zip -qq -r -0 ./.build/MolliePayments.zip MolliePayments/* -x '*/vendor/*' '*.editorconfig' '*.git*' '*.reports*' '*/.idea*' '*/tests*' '*/node_modules*' '*/makefile' '*.DS_Store' '*/.shopware-extension.yml' '*/phpunit.xml' '*/.phpunuhi.xml' '*/.infection.json' '*/phpunit.autoload.php' '*/.phpstan*' '*/.php_cs.php' '*/phpinsights.php'
	# -------------------------------------------------------------------------------------------------
	# -------------------------------------------------------------------------------------------------
	@echo ""
	@echo "CONGRATULATIONS"
	@echo "The new ZIP file is available at plugins/.build/MolliePayments.zip"
