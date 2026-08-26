#
# Makefile
#
.PHONY: help prod dev clean build fixtures pr release validate report build-js
.DEFAULT_GOAL := help
PLUGIN_VERSION = $(shell php -r 'echo json_decode(file_get_contents("composer.json"))->version;')

NODE_VERSION:=$(shell node -v)

# Tool set for "make validate". A literal comma cannot be written inside a make
# function call, so this is resolved here instead of inline in the recipe.
ifdef only
VALIDATE_TOOLS := $(only)
else
VALIDATE_TOOLS := sw-cli,phpstan
endif

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
	rm -rf ./src/Resources/app/administration/node_modules
	rm -rf ./src/Resources/app/storefront/node_modules
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
	@php vendor/bin/phpunit --configuration=./config/phpunit.xml

phpintegration: ##3 Starts all PHPUnit Tests [groups=core to limit to a group]
	@cd ../../.. && php vendor/bin/phpunit --configuration=./custom/plugins/MolliePayments/config/phpunit.integration.xml $(if $(groups),--group $(groups),)

report: ##3 Runs the unit tests with coverage and prints the line coverage of shopware/ and src/
	# ----------------------------------------------------------------
	# reset previous report output
	rm -rf ./.reports/phpunit/coverage ./.reports/phpunit/clover.xml ../../../public/coverage
	mkdir -p ./.reports/phpunit
	# ----------------------------------------------------------------
	# pcov records nothing outside pcov.directory, and the dockware image points that
	# at the Shopware core - which is why every plugin file used to come back as 0%.
	# Overriding XDEBUG_MODE instead would not help: php-code-coverage picks pcov
	# over Xdebug whenever pcov is loaded (see Driver/Selector.php).
	# XDEBUG_MODE is the fallback for an image without pcov: php-code-coverage picks pcov
	# whenever it is loaded, so this has no effect on dockware, but without it a
	# pcov-less environment would produce no report at all.
	@XDEBUG_MODE=coverage php -d pcov.enabled=1 -d pcov.directory=$(CURDIR) \
		vendor/bin/phpunit --configuration=./config/phpunit.xml \
		--coverage-html ./.reports/phpunit/coverage \
		--coverage-clover ./.reports/phpunit/clover.xml
	# PHPUnit only warns when no coverage driver is available and still exits 0, which
	# would leave the summary below reporting a truthful-looking 0.00%. Fail loudly instead.
	@test -f ./.reports/phpunit/clover.xml || { echo ""; echo "  No coverage was recorded - is pcov or Xdebug available?"; exit 1; }
	@php -r '$$m = simplexml_load_file("./.reports/phpunit/clover.xml")->project->metrics; $$s = (int) $$m["statements"]; $$c = (int) $$m["coveredstatements"]; printf("%s  Line coverage: %.2f%% (%d/%d statements)%s%s", PHP_EOL, $$s > 0 ? $$c / $$s * 100 : 0, $$c, $$s, PHP_EOL, PHP_EOL);'
	# ----------------------------------------------------------------
	# Publish the HTML report into the Shopware document root, so it can be opened
	# in the browser at <shop-url>/coverage instead of only from the file system.
	@cp -r ./.reports/phpunit/coverage ../../../public/coverage
	@echo "  HTML report at .reports/phpunit/coverage/index.html"
	@echo "  HTML report in the browser at <shop-url>/coverage"
	@echo ""

# Behat prints progress to stdout. CI passes allure=<dir> to additionally write Allure
# results; --out maps to --format by position, and `std` is Behat's word for stdout.
BEHAT_OUTPUT := --format=progress
ifdef allure
BEHAT_OUTPUT := --format=progress --format=allure --out=std --out=$(allure)
endif

behat: ##3 Starts all Behat Tests [allure=<dir> also writes Allure results]
	cd ../../.. && php vendor/bin/behat --config ./custom/plugins/MolliePayments/config/behat.yaml $(BEHAT_OUTPUT) --colors

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
	# An empty node_modules makes shopware-cli skip npm install, producing a ZIP
	# without admin assets (broken backend on customer update). Remove it so the
	# asset build always installs fresh.
	rm -rf ./src/Resources/app/administration/node_modules
	rm -rf ./src/Resources/app/storefront/node_modules
	docker run --rm \
		--user "$(shell id -u):$(shell id -g)" \
		-e HOME=/tmp \
		-v "$(CURDIR)/..":/plugins \
		-v "$(CURDIR)/config/.shopware-extension.yml":/plugins/MolliePayments/.shopware-extension.yml \
		-w /plugins/.build \
		ghcr.io/shopware/shopware-cli:latest \
		extension zip /plugins/MolliePayments --disable-git
	# Make the same ZIP work on Shopware 6.5.x as well:
	#   - 6.6/6.7 load the nested  js/<name>/<name>.js  (built above via constraint >=6.6.0.0)
	#   - 6.5     loads  the flat  js/<name>.js
	# The shopware-cli esbuild bundle is byte-identical in both layouts (only the
	# output path differs), so we copy the freshly built nested file to the flat
	# 6.5 path inside the ZIP instead of running a second build.
	cd ../.build && \
		ZIP=$$(ls MolliePayments*.zip | head -1) && \
		JS=MolliePayments/src/Resources/app/storefront/dist/storefront/js && \
		unzip -o -q "$$ZIP" "$$JS/mollie-payments/mollie-payments.js" && \
		cp "$$JS/mollie-payments/mollie-payments.js" "$$JS/mollie-payments.js" && \
		zip -q "$$ZIP" "$$JS/mollie-payments.js" && \
		rm -rf MolliePayments
	@echo ""
	@echo "CONGRATULATIONS"
	@echo "ZIP file available at plugins/.build/"

validate: ##4 Runs the Shopware extension verifier against a release ZIP [zip=<path>, only=<tools>, reporter=<format>]
	# This is the same tooling the Shopware Store runs on upload, so it catches
	# rejections before we submit. It deliberately validates the ZIP and not the
	# source directory: only the ZIP carries the mounted .shopware-extension.yml
	# (and its validation.ignore list), and only the ZIP is free of the vendor
	# directory - with a vendor/ present the verifier skips dependency resolution
	# and every Shopware class would come back as "not found".
	#
	# Exit code is non-zero for error-level findings only; warnings are printed
	# but do not fail the build, which mirrors what the Store does on upload.
	#
	# Default tool set:
	#   phpstan  is what the Store shows as "Statische Code Analyse"
	#   sw-cli   is a tool in the same --only list, not a separate mode - dropping
	#            it would also drop metadata.icon.size, packaging and snippet checks
	# ESLint, Stylelint and the Twig linters are left out on purpose: they already
	# run as their own jobs in step_review.yml. Override with only=<tools> or
	# only=all to run everything.
	@ZIP="$(zip)"; \
	if [ -z "$$ZIP" ]; then ZIP=$$(ls ../.build/MolliePayments*.zip 2>/dev/null | head -1); fi; \
	if [ ! -f "$$ZIP" ]; then \
		echo "No release ZIP found. Run 'make release -B' first, or pass zip=<path>."; \
		exit 1; \
	fi; \
	echo "Validating $$ZIP"; \
	docker run --rm \
		--user "$(shell id -u):$(shell id -g)" \
		-e HOME=/tmp \
		-v "$$(cd "$$(dirname "$$ZIP")" && pwd)":/build \
		ghcr.io/shopware/shopware-cli:latest \
		extension validate --full "/build/$$(basename "$$ZIP")" \
		$(if $(filter-out all,$(VALIDATE_TOOLS)),--only "$(VALIDATE_TOOLS)",) \
		$(if $(reporter),--reporter $(reporter),)

build-js:
	rm -rf "./src/Resources/public/administration/assets"
	rm -rf "./src/Resources/public/administration/.vite"
	docker run --rm \
    -v "./..:/plugins" \
    -v "./config/.shopware-extension.yml:/plugins/MolliePayments/.shopware-extension.yml" \
    ghcr.io/shopware/shopware-cli:latest \
    extension build /plugins/MolliePayments
	@JS_DIR="./src/Resources/app/storefront/dist/storefront/js"; \
	if [ -f "$$JS_DIR/mollie-payments.js" ]; then \
	    mkdir -p "$$JS_DIR/mollie-payments" && \
	    cp "$$JS_DIR/mollie-payments.js" "$$JS_DIR/mollie-payments/mollie-payments.js" && \
	    { [ -f "$$JS_DIR/mollie-payments.js.map" ] && cp "$$JS_DIR/mollie-payments.js.map" "$$JS_DIR/mollie-payments/mollie-payments.js.map" || true; }; \
	fi
