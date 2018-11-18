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

#build dev image where dashboards templates must be passed as volumes
docker build --file images/kibana/DockerfileDev \
             --tag ${DOCKER_REGISTRY}/kibana:dev \
             $1 images/kibana

#build prod image where dashboards templates are within image
#you only have to pass configuration as a volume
docker build --file images/kibana/Dockerfile \
             --tag ${DOCKER_REGISTRY}/kibana:prod \
             --build-arg BASE_IMAGE=${DOCKER_REGISTRY}/kibana:dev $1 images/kibana

printf "${GREEN}Built Docker images for Kibana!${NC}\n"
