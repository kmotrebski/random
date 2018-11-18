#!/bin/bash
set -e

# Pushes Docker images to AWS ECR Docker registry.
# Use build.sh script if you want to build images from scratch.
# Script must be run from project top directory(!)

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

if [ ! -f .env ]; then
    printf "${RED}.env file is missing. Use new_developer.sh script first!${NC}\n"
    exit 2
fi

source ./load_variables.sh
source ./.env

# login to ECR
#eval $(aws ecr get-login --no-include-email --region ${AWS_REGION} | sed 's|https://||')
$(aws ecr get-login --no-include-email --region ${AWS_REGION})

# push images
docker pull ${DOCKER_REGISTRY}/fluentd:dev
docker pull ${DOCKER_REGISTRY}/fluentd:prod

printf "${GREEN}Successfully pulled images from AWS ECR!${NC}\n"
