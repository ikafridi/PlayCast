.PHONY: *

list:
	@LC_ALL=C $(MAKE) -pRrq -f $(lastword $(MAKEFILE_LIST)) : 2>/dev/null | awk -v RS= -F: '/^# File/,/^# Finished Make data base/ {if ($$1 !~ "^[#.]") {print $$1}}' | sort | egrep -v -e '^[^[:alnum:]]' -e '^$@$$'

up:
	docker-compose up -d

down:
	docker-compose down

restart: down up

build: # Rebuild all containers and restart
	docker-compose build
	$(MAKE) restart

update: # Update everything (i.e. after a branch update)
	docker-compose build
	$(MAKE) down
	docker-compose run --rm --user=playcast web composer install
	docker-compose run --rm --user=playcast web playcast_cli playcast:setup:initialize
	$(MAKE) frontend-build
	$(MAKE) up

test:
	docker-compose run --rm --user=playcast web composer run cleanup-and-test

bash:
	docker-compose run --rm --user=playcast web bash

frontend-bash:
	docker-compose -f frontend/docker-compose.yml build
	docker-compose -f frontend/docker-compose.yml run --rm frontend

frontend-build:
	docker-compose -f frontend/docker-compose.yml build
	docker-compose -f frontend/docker-compose.yml run --rm frontend npm run dev-build

