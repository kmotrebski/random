#!/bin/bash
set -ev

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

source ./load_variables.sh
source ./.env

if [ "${IS_CICD}" = "true" ]; then
    SSH_VOLUME_MAPPING=~/.ssh:/root/.ssh
else
    LOCAL_USER=$(id -u -n)
    SSH_VOLUME_MAPPING=~/.ssh:/home/${LOCAL_USER}/.ssh
fi

COMMAND=install

# (1) read test scope if provided in command line
if [ "$1" != "" ] ; then
    COMMAND=$1
fi

docker run --rm \
    --name composer \
    --volume $PWD/images/fluentd:/app \
    --env SSH_AUTH_SOCK=/ssh-auth.sock \
    --user $(id -u):$(id -g) \
    --volume $SSH_AUTH_SOCK:/ssh-auth.sock \
    --volume /etc/passwd:/etc/passwd:ro \
    --volume /etc/group:/etc/group:ro \
    --volume $SSH_VOLUME_MAPPING \
    --volume ${PHP_COMPOSER_CACHE_DIR}:/tmp/cache \
    composer:1.6.3 ${COMMAND} --no-interaction --ignore-platform-reqs -vvv

printf "${GREEN}Started installing PHP dependencies for automated tests.${NC}\n"
