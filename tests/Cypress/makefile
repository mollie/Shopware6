.PHONY: help
.DEFAULT_GOAL := help


help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ---------------------------------------------------------------------------------------------

install: ## Installs all dependencies
	npm install cypress --save-dev --save .
	npm install webpack --save-dev --save .
	npm install @cypress/webpack-preprocessor --save-dev --save .
	npm install @cucumber/tag-expressions --save-dev --save .
	npm install axios --save-dev --save .

# ---------------------------------------------------------------------------------------------

open-ui: ## Opens Cypress UI
ifndef url
	$(warning Please provide the URL argument to Start Cypress!)
	@exit 1
endif
	CYPRESS_BASE_URL=$(url) ./node_modules/.bin/cypress open --env conf=dev

# ---------------------------------------------------------------------------------------------

run: ## Runs all E2E tests for Shopware >= 6.4
ifndef url
	$(warning Please provide the URL argument to Start Cypress!)
	@exit 1
endif
	CYPRESS_BASE_URL=$(url) ./node_modules/.bin/cypress run --env conf=dev --headless --spec 'cypress/integration/6.4/**/*'

run-old: ## Runs all E2E tests for Shopware < 6.4
ifndef url
	$(warning Please provide the URL argument to Start Cypress!)
	@exit 1
endif
	CYPRESS_BASE_URL=$(url) ./node_modules/.bin/cypress run --env conf=dev --headless --spec 'cypress/integration/old/**/*'
