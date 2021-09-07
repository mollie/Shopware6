.PHONY: help
.DEFAULT_GOAL := help


help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ---------------------------------------------------------------------------------------------

install: ## Installs all dependencies
	npm install cypress --save-dev --save .
	npm install webpack@4.18.1 --save-dev --save .
	npm install @cypress/webpack-preprocessor --save-dev --save .
	npm install @cucumber/tag-expressions --save-dev --save .
	npm install axios --save-dev --save .

clean: ## Clean all dependencies
	rm -rf node_modules 
	rm -rf package.json
	rm -rf package-lock.json

# ---------------------------------------------------------------------------------------------

open-ui: ## Opens Cypress UI
ifndef url
	$(warning Please provide the URL argument to Start Cypress!)
	@exit 1
endif
ifndef shopware
	CYPRESS_BASE_URL=$(url) CYPRESS_SHOPWARE=6.4 ./node_modules/.bin/cypress open --env conf=dev
else
	CYPRESS_BASE_URL=$(url) CYPRESS_SHOPWARE=$(shopware) ./node_modules/.bin/cypress open --env conf=dev
endif

# ---------------------------------------------------------------------------------------------

run: ## Runs all E2E tests for Shopware
ifndef url
	$(warning Please provide the URL argument to Start Cypress!)
	@exit 1
endif
ifndef shopware
	$(warning Please provide the Shopware Version as argument to Start Cypress!)
	@exit 1
endif
	CYPRESS_BASE_URL=$(url) CYPRESS_SHOPWARE=$(shopware) ./node_modules/.bin/cypress run --env conf=dev --headless
