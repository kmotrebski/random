#!/bin/bash
set -ve

# Builds Docker images from scratch.
# add --no-cache docker-like flag to pass it to "docker build" command.
# Use push.sh script if you want to push images to AWS ECR.
# Script must be run from project top directory(!)

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

if [ ! -f .env ]; then
    printf "${RED}.env file is missing. Use new_developer.sh script first!${NC}\n"
    exit 2
fi

source ./.env

# build dev image
docker build --file images/fluentd/docker/DockerfileDev \
             --tag ${DOCKER_REGISTRY}/fluentd:dev \
             --build-arg BASE_IMAGE=fluent/fluentd:v1.3.2-debian-1.0 \
             $1 images/fluentd

# build prod image
docker build --file images/fluentd/docker/Dockerfile \
             --tag ${DOCKER_REGISTRY}/fluentd:prod \
             --build-arg BASE_IMAGE=${DOCKER_REGISTRY}/fluentd:dev \
             $1 images/fluentd

printf "${GREEN}Built Docker images for Fluentd service!${NC}\n"
