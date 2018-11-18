#!/usr/bin/env bash
set -e

#colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m'

FILE=images/kibana/docker-compose-test.yaml

source ./images/kibana/test.config

docker-compose -f ${FILE} -p kibana_test up -d

ES_HOST=localhost
ES_PORT=9200

until $(curl --output /dev/null --silent --head --fail ${ES_HOST}:${ES_PORT}); do
    printf "${YELLOW}Waiting for Elasticsearch...${NC}\n"
    sleep 0.5
done

#test 1: configuration is indexed

until curl -X GET -s ${ES_HOST}:${ES_PORT}/.kibana/config/5.2.2 | jq '. | .found' | grep -q "true" ; do
    printf "${YELLOW}Waiting for configuration to be indexed...${NC}\n"
    sleep 1
done

printf "${GREEN}Configuration is indexed!${NC}\n"

#test 2: index-pattern is indexed

until curl -X GET -s ${ES_HOST}:${ES_PORT}/.kibana/index-pattern/${INDEX_PATTERN} | jq '. | .found' | grep -q "true" ; do
    printf "${YELLOW}Waiting for index-pattern to be indexed...${NC}\n"
    sleep 1
done

printf "${GREEN}Index-pattern is indexed!${NC}\n"

#final output

curl -XGET ${ES_HOST}:${ES_PORT}/_cat/indices?pretty
curl -XGET ${ES_HOST}:${ES_PORT}/.kibana/_search?pretty

printf "${GREEN}All tests passed!${NC}\n"

docker-compose -f ${FILE} -p kibana_test down

exit 0;
