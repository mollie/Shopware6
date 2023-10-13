
MOLLIE_PHP_VERSION:=2.61.0


help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ------------------------------------------------------------------------------------------------------------

install: ## Installs all production dependencies
	rm -rf mollie
	git clone -b v$(MOLLIE_PHP_VERSION) https://github.com/mollie/mollie-api-php.git mollie/mollie-api-php
	rm -rf mollie/mollie-api-php/.git
	rm -rf mollie/mollie-api-php/.github
	rm -rf mollie/mollie-api-php/.gitattributes
	rm -rf mollie/mollie-api-php/.gitignore
	rm -rf mollie/mollie-api-php/examples
	rm -rf mollie/mollie-api-php/tests
