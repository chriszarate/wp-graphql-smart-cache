The wp-graphql-persisted-queries plugin.

Local development with docker to build the app and a local running WordPress. As well as run the test suites.

# Plugin

## Build

Use one of the following commands to build the plugin source and it's dependencies. Do this at least once after initial checkout or after updating composer.json.

    composer install --optimize-autoloader

    composer update --optimize-autoloader

# Docker App Image

This section describes how to setup and run this plugin, WP and the wp-graphql plugin locally with docker.  It requires building the images at least once, which can take a few moments the first time. 

## Build

### docker-compose build

Use one of the following commands to build the local images for the app and teting.

Build all images in the docker compose configuration.

    WP_VERSION=5.7.2  PHP_VERSION=7.4 docker-compose build --build-arg WP_VERSION=5.7.2 --build-arg PHP_VERSION=7.4

Build fresh docker image without cache by adding `--no-cache`.

    WP_VERSION=5.7.2  PHP_VERSION=7.4 docker-compose build --build-arg WP_VERSION=5.7.2 --build-arg PHP_VERSION=7.4 --no-cache

Build using wp-graphql image from docker hub registry, instead of building your own wp-graphql image.

    WP_VERSION=5.7.2  PHP_VERSION=7.4 docker-compose build --build-arg WP_VERSION=5.7.2 --build-arg PHP_VERSION=7.4 --build-arg DOCKER_REGISTRY=ghcr.io/wp-graphql/

### docker build

Use this command if you want to build a specific image. If you ran the docker-compose command above, this is not necessary.

    docker build -f docker/Dockerfile -t wp-graphql-persisted-queries:latest-wp5.6-php7.4 --build-arg WP_VERSION=5.6 --build-arg PHP_VERSION=7.4

## Run

Use one of the following to start the WP app with the plugin installed and running. After running, navigate to the app in a web browser at http://localhost:8091/

    docker compose up app

This is an example of specifying the WP and PHP version for the wp-graphql images.

    docker compose up -e WP_VERSION=5.7.2 -e PHP_VERSION=7.4 app

## Shell

Use one of the following if you want to access the WP app with bash command shell.

    docker-compose run app bash

    docker-compose run -e WP_VERSION=5.7.2 -e PHP_VERSION=7.4 app bash

## Stop

Use this command to stop the running app and database.

    docker-compose stop

## Attach local wp-graphql plugin

Add this to volumes section in docker-compose.yml if you have a copy of the wp-graphql plugin you'd like to use in the running app. 

      - './local-wp-graphql:/var/www/html/wp-content/plugins/wp-graphql'

# WP Tests

Use this section to run the plugin codeception test suites.

## Build

Use one of the following commands to build the test docker image. If you ran the docker-compose build command, above, this step is not necessary and you should already have the build docker image, skip to run.

### docker-compose build

    WP_VERSION=5.7.2 PHP_VERSION=7.4 docker build -f Dockerfile.testing -t wp-graphql-persisted-queries-testing:latest-wp${WP_VERSION}-php${PHP_VERSION} --build-arg WP_VERSION=${WP_VERSION} --build-arg PHP_VERSION=${PHP_VERSION}

### docker build

    docker build -f docker/Dockerfile.testing -t wp-graphql-persisted-queries-testing:latest-wp5.7.2-php7.4 --build-arg WP_VERSION=5.7.2 --build-arg PHP_VERSION=7.4

## Run

Use this command to run the test suites.

    WP_VERSION=5.7.2 PHP_VERSION=7.4 SUITES=acceptance,functional docker-compose run testing

## Shell

Use one of the following if you want to access the WP testing app with bash command shell.

    docker-compose run --entrypoint bash testing

This is an example of specifying the WP and PHP version for the wp-graphql images.

    docker-compose run -e WP_VERSION=5.7.2 -e PHP_VERSION=7.4 --entrypoint bash testing
