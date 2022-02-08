.PHONY: help
.DEFAULT_GOAL := help


help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

# ---------------------------------------------------------------------------------------------

run: ## Starts a Docker container with Swagger
	docker run --rm -p 8080:8080 -v ${PWD}/mollie.yaml:/usr/share/nginx/html/mollie.yaml -v ${PWD}/mollie-headless.yaml:/usr/share/nginx/html/mollie-headless.yaml -e PERSIST_AUTHORIZATION="true" -e URLS="[ { name: 'Mollie Plugin API', url: '/mollie.yaml' }, { name: 'Mollie Headless API', url: '/mollie-headless.yaml' } ]" --name swagger -d swaggerapi/swagger-ui

stop: ## Stops the Docker container
	docker rm -f swagger
