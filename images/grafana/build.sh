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

docker build --file images/grafana/Dockerfile \
             --tag ${DOCKER_REGISTRY}/grafana:latest \
             $1 images/grafana

printf "${GREEN}Built Docker images for Grafana!${NC}\n"
