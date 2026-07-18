.DEFAULT_GOAL := help

EXT_DIR := extension
SERVER_DIR := server
DIST := dist
PORT ?= 8080
PHP ?= php
DOCKER_IMAGE := cleanroom-server
E2E_CONTAINER := cleanroom-e2e
E2E_PORT ?= 18080
TRY_PORT ?= 18081

.PHONY: help test build build-extension build-server run dev lint clean docker-build docker-run e2e try

help: ## Show available targets
	@grep -E '^[a-zA-Z0-9_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-15s %s\n", $$1, $$2}'

test: ## Run the server test suite
	cd $(SERVER_DIR) && $(PHP) tests/test_sanitize.php
	cd $(SERVER_DIR) && $(PHP) tests/test_server.php

build: build-extension build-server ## Build everything into dist/

build-extension: ## Package the extension for the Chrome Web Store (dist/cleanroom-extension.zip)
	mkdir -p $(DIST)
	rm -f $(DIST)/cleanroom-extension.zip
	cd $(EXT_DIR) && zip -r -q ../$(DIST)/cleanroom-extension.zip manifest.json background.js options.html options.js icons
	@ls -lh $(DIST)/cleanroom-extension.zip

build-server: ## Assemble the deployable server (dist/server/, upload its contents to Dreamhost)
	mkdir -p $(DIST)/server
	cp $(SERVER_DIR)/index.php $(SERVER_DIR)/.htaccess $(SERVER_DIR)/favicon.ico $(SERVER_DIR)/favicon.gif $(SERVER_DIR)/logo.svg $(DIST)/server/
	@ls -lah $(DIST)/server

run: build-server ## Build, then serve the built server locally (override with PORT=...)
	$(PHP) -S 127.0.0.1:$(PORT) -t $(DIST)/server $(SERVER_DIR)/router.php

dev: ## Serve the server source directly (override with PORT=...)
	$(PHP) -S 127.0.0.1:$(PORT) -t $(SERVER_DIR) $(SERVER_DIR)/router.php

lint: ## Syntax-check the PHP and lint the JS
	$(PHP) -l $(SERVER_DIR)/index.php
	@for f in $(SERVER_DIR)/tests/*.php; do $(PHP) -l $$f || exit 1; done
	npx eslint $(EXT_DIR) e2e scripts

docker-build: ## Build the server Docker image (Apache + PHP, like Dreamhost)
	docker build -t $(DOCKER_IMAGE) $(SERVER_DIR)

docker-run: docker-build ## Run the server in Docker on PORT (Ctrl+C to stop)
	docker run --rm -p $(PORT):80 --add-host=host.docker.internal:host-gateway $(DOCKER_IMAGE)

e2e: docker-build ## Run the Playwright suite against the Dockerized server
	npm install
	npx playwright install chromium
	-docker rm -f $(E2E_CONTAINER) >/dev/null 2>&1
	docker run -d --rm --name $(E2E_CONTAINER) -p $(E2E_PORT):80 -e CLEANROOM_ALLOW_PRIVATE=1 --add-host=host.docker.internal:host-gateway $(DOCKER_IMAGE)
	@for i in $$(seq 1 20); do curl -s -o /dev/null http://localhost:$(E2E_PORT)/ && break; sleep 0.5; done
	BASE_URL=http://localhost:$(E2E_PORT) npx playwright test -c e2e/playwright.config.js || (docker rm -f $(E2E_CONTAINER); exit 1)
	BASE_URL=http://localhost:$(E2E_PORT)/ CLEANROOM_TRY_SMOKE=1 node scripts/try.js || (docker rm -f $(E2E_CONTAINER); exit 1)
	docker rm -f $(E2E_CONTAINER)

try: docker-build ## Open a browser with the extension loaded against a local Dockerized server
	npm install
	npx playwright install chromium
	-docker rm -f cleanroom-try >/dev/null 2>&1
	docker run -d --rm --name cleanroom-try -p $(TRY_PORT):80 --add-host=host.docker.internal:host-gateway $(DOCKER_IMAGE)
	@for i in $$(seq 1 20); do curl -s -o /dev/null http://localhost:$(TRY_PORT)/ && break; sleep 0.5; done
	BASE_URL=http://localhost:$(TRY_PORT)/ node scripts/try.js; docker rm -f cleanroom-try

clean: ## Remove build output
	rm -rf $(DIST)
