#
# Makefile
#

.PHONY: help
.DEFAULT_GOAL := help

PLUGIN_VERSION=`cat MolliePayments/composer.json | grep -oP '(?<="version": ")[^"]*'`

help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ------------------------------------------------------------------------------------------------------------

install: ## Installs all production dependencies
	@composer install --no-dev

dev: ## Installs all dev dependencies
	@composer install

clean: ## Cleans all dependencies
	rm -rf vendor
	rm -rf .reports | true

# ------------------------------------------------------------------------------------------------------------
test: ## Starts all Tests
	php vendor/bin/phpunit --configuration=phpunit.xml

# ------------------------------------------------------------------------------------------------------------

release: ## Creates a new ZIP package
	@cd .. && zip -qq -r -0 MolliePayments-$(PLUGIN_VERSION).zip MolliePayments/ -x '*.git*' '*.reports*' '*.travis.yml*' '*/tests*' '*/makefile' '*.DS_Store'
