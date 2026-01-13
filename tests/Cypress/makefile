.PHONY: help
.DEFAULT_GOAL := help


help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ---------------------------------------------------------------------------------------------

install: ## Installs all dependencies
	npm install

clean: ## Clean all dependencies
	rm -rf node_modules

# ---------------------------------------------------------------------------------------------

open-ui: ## Opens Cypress UI
ifndef url
	$(warning please use url= as argument)
	@exit 1
endif
ifndef shopware
	$(warning please use shopware= as argument)
	@exit 1
endif
ifndef storeApiKey
	$(warning please use storeApiKey= as argument)
	@exit 1
endif
	@echo ""
	@echo "*********************************************************************************************************"
	@echo "ATTENTION, please enable MOLLIE_CYPRESS_MODE=1 in your .env to allow subscriptions without webhooks, if you cant provide webhooks!"
	@echo "*********************************************************************************************************"
	@echo ""
	CYPRESS_BASE_URL=$(url) CYPRESS_SHOPWARE=$(shopware) CYPRESS_STORE_APIKEY=$(storeApiKey) ./node_modules/.bin/cypress open

# ---------------------------------------------------------------------------------------------

run: ## Runs all E2E tests for Shopware
ifndef url
	$(warning please use url= as argument)
	@exit 1
endif
ifndef shopware
	$(warning please use shopware= as argument)
	@exit 1
endif
ifndef storeApiKey
	$(warning please use storeApiKey= as argument)
	@exit 1
endif
	@echo ""
	@echo "*********************************************************************************************************"
	@echo "ATTENTION, please enable MOLLIE_CYPRESS_MODE=1 in your .env to allow subscriptions without webhooks, if you cant provide webhooks!"
	@echo "*********************************************************************************************************"
	@echo ""
	CYPRESS_BASE_URL=$(url) CYPRESS_SHOPWARE=$(shopware) CYPRESS_STORE_APIKEY=$(storeApiKey) ./node_modules/.bin/cypress run --headless --env filters="$(filters)"

parallel: ## Runs all E2E tests for Shopware in parallel
ifndef url
	$(warning Please provide the URL argument to Start Cypress!)
	@exit 1
endif
ifndef shopware
	$(warning Please provide the Shopware Version as argument to Start Cypress!)
	@exit 1
endif
ifndef storeApiKey
	$(warning please use storeApiKey= as argument)
	@exit 1
endif
	@echo ""
	@echo "*********************************************************************************************************"
	@echo "ATTENTION, please enable MOLLIE_CYPRESS_MODE=1 in your .env to allow subscriptions without webhooks, if you cant provide webhooks!"
	@echo "*********************************************************************************************************"
	@echo ""
	CYPRESS_BASE_URL=$(url) CYPRESS_SHOPWARE=$(shopware) CYPRESS_STORE_APIKEY=$(storeApiKey) npx concurrently \
		"./node_modules/.bin/cypress run --headless --env filters=\"$(filters)\",split=3,splitIndex=0" \
		"./node_modules/.bin/cypress run --headless --env filters=\"$(filters)\",split=3,splitIndex=1" \
		"./node_modules/.bin/cypress run --headless --env filters=\"$(filters)\",split=3,splitIndex=2"