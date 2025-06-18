#
# Makefile
#
.PHONY: help
.DEFAULT_GOAL := help
PLUGIN_VERSION = $(shell php -r 'echo json_decode(file_get_contents("composer.json"))->version;')

NODE_VERSION:=$(shell node -v)



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
	composer install
	npm install
	chmod a+x node_modules/.bin/prettier
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
	rm -f .shopware-extension.yml
ifndef nossl
	# importan, first we run build wit 6.7 because it removes files from public/administration, the build before 6.7 does not removes them
	cp ./config/.shopware-extension-6.7.yml .shopware-extension.yml
	cd ../../.. && export NODE_OPTIONS=--openssl-legacy-provider && shopware-cli extension build custom/plugins/MolliePayments
	cp ./config/.shopware-extension.yml .shopware-extension.yml
	cd ../../.. && export NODE_OPTIONS=--openssl-legacy-provider && shopware-cli extension build custom/plugins/MolliePayments
endif
ifeq ($(nossl),true)
	cp ./config/.shopware-extension-6.7.yml .shopware-extension.yml
	cd ../../.. && shopware-cli extension build custom/plugins/MolliePayments
	cp ./config/.shopware-extension.yml .shopware-extension.yml
	cd ../../.. && shopware-cli extension build custom/plugins/MolliePayments
endif
	rm -f .shopware-extension.yml
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
	@make prettier mode=fix -B
	# -----------------------------------------------------------------
	# VALIDATORS
	@make phpcheck -B
	@make phpmin -B
	@make stan -B
	@make phpunit -B
	@make phpintegration -B
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
	@php vendor/bin/phpcs -p --standard=PHPCompatibility --extensions=php --runtime-set testVersion 8.0 ./src ./shopware

csfix: ##3 Starts the PHP CS Fixer
ifndef mode
	@PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=./config/.php_cs.php --dry-run --show-progress=dots --verbose
endif
ifeq ($(mode), fix)
	@PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix --config=./config/.php_cs.php --show-progress=dots --verbose
endif

stan: ##3 Starts the PHPStan Analyser
	cd ../../.. && php vendor/bin/phpstan analyse -c ./custom/plugins/MolliePayments/config/.phpstan.neon

phpunit: ##3 Starts all PHPUnit Tests
	@XDEBUG_MODE=coverage php vendor/bin/phpunit --testsuite unit --configuration=./config/phpunit.xml --coverage-html ./.reports/phpunit/coverage

phpintegration: ##3 Starts all PHPUnit Tests
	#we call "real" phpunit, it seems like in sw 6.4 the vendor/bin/phpunit is overwritten by shopware
	@XDEBUG_MODE=coverage cd ../../.. && php vendor/phpunit/phpunit/phpunit --testsuite integration --configuration=./custom/plugins/MolliePayments/config/phpunit.xml

insights: ##3 Starts the PHPInsights Analyser
	@php vendor/bin/phpinsights analyse --no-interaction

vitest: ##3 Starts all Vitest tests
	npx vitest -c ./config/vitest.config.ts

eslint: ##3 Starts the ESLinter
ifndef mode
	./node_modules/.bin/eslint --config ./config/.eslintrc.json ./src/Resources/app
endif
ifeq ($(mode), fix)
	./node_modules/.bin/eslint --config ./config/.eslintrc.json ./src/Resources/app --fix
endif

stylelint: ##3 Starts the Stylelinter
ifndef mode
	./node_modules/.bin/stylelint --allow-empty-input ./src/Resources/app/**/*.scss --config=./config/.stylelintrc
endif
ifeq ($(mode), fix)
	./node_modules/.bin/stylelint --allow-empty-input ./src/Resources/app/**/*.scss --fix --config=./config/.stylelintrc
endif

prettier: ##3 Starts the Prettier
ifndef mode
	./node_modules/.bin/prettier ./src/Resources/app/ --config=./config/.prettierrc  --check
endif
ifeq ($(mode), fix)
	./node_modules/.bin/prettier ./src/Resources/app/ --config=./config/.prettierrc  --write
endif

configcheck: ##3 Tests and verifies the plugin configuration file
	cd ./tests/Custom && php verify-plugin-config.php

phpunuhi: ##3 Tests and verifies all plugin snippets
	php vendor/bin/phpunuhi validate --configuration=./config/.phpunuhi.xml --report-format=junit --report-output=./.phpunuhi/junit.xml

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
	@echo "Show build artifacts"
	ls -la ./src/Resources/public/administration
	# -------------------------------------------------------------------------------------------------
	@echo "INSTALL PRODUCTION DEPENDENCIES"
	make prod -B
	rm -rf ./src/Resources/app/storefront/node_modules/*
	# DELETE distribution file. that ones not compatible between 6.5 and 6.4
	# if one wants to use it, they need to run build-storefront.sh manually and activate that feature
	# in our plugin configuration! (use shopware standard js)
	rm -rf ./src/Resources/app/storefront/dist/storefront
	@echo "Show build artifacts"
	ls -la ./src/Resources/public/administration
	# -------------------------------------------------------------------------------------------------
	@echo "CREATE ZIP FILE"
	cd .. && zip -qq -r -0 ./.build/MolliePayments.zip MolliePayments/ -x '*/vendor/*'  '*.git*' '*.reports*' '*/.idea*' '*/tests*' '*/node_modules*'  '*/.phpunuhi*' '*/makefile' '*.DS_Store' 'config/*' '*.prettierignore' './package.json' './package-lock.json'
	cd .. && zip -qq -r -0 ./.build/MolliePayments-e2e.zip MolliePayments/ -x '*/vendor/*'  '*.git*' '*.reports*' '*/.idea*' '*/node_modules*'  '*/.phpunuhi*' '*.DS_Store' '*.prettierignore' './package.json' './package-lock.json'
	# -------------------------------------------------------------------------------------------------
	# -------------------------------------------------------------------------------------------------
	@echo ""
	@echo "CONGRATULATIONS"
	@echo "The new ZIP file is available at plugins/.build/MolliePayments.zip"
