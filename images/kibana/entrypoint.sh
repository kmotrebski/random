#!/usr/bin/env bash
set -ev

#colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m'

#(0) require configuration file

if [ ! -f /usr/share/otrebski/config ]; then
    printf "${RED}Configuration file /usr/share/otrebski/config is missing!${NC}\n"
    exit 2
fi

source /usr/share/otrebski/config

#(1) require templates file

if [ ! -f /usr/share/otrebski/templates/data.json ]; then
    printf "${RED}Template file /usr/share/otrebski/templates/data.json is missing!${NC}\n"
    exit 3
fi

if [ ! -f /usr/share/otrebski/templates/mappings.json ]; then
    printf "${RED}Template file /usr/share/otrebski/templates/mappings.json is missing!${NC}\n"
    exit 3
fi

#(2) create definition file from template

#(2a) interpolate configuration values into template

#todo maybe it will be cleaner not to create a tmp file but make sed to use variables only?
cat /usr/share/otrebski/templates/data.json > /tmp/otrebski_data.json

CONFIGS=`cat /usr/share/otrebski/config`

#from https://stackoverflow.com/questions/21056450/how-to-inject-environment-variables-in-varnish-configuration/21062584
for CONFIG in $CONFIGS
do
    IFS== read name value <<< "$CONFIG"
    sed -i "s|\${${name}}|${value}|g" /tmp/otrebski_data.json
done

#(2b) interpolate index patterns into template
PATTERNS_VALUE=`cat /usr/share/otrebski/templates/app_logs_pattern.json | jq '.|tojson' | jq '.|tojson'`

sed -i "s|\${APP_LOGS_PATTERN_AS_JSON}|${PATTERNS_VALUE}|g" /tmp/otrebski_data.json

#remove duplicated double quotes that ware generated in the above sed substitution
sed -i "s|\"\"|\"|g" /tmp/otrebski_data.json

#compact into single line JSONs as this is requirement of Elasticsearch batch API
cat /tmp/otrebski_data.json | jq --compact-output '.' > /tmp/otrebski_data_compact.json

#(3) wait for elasticsearch

until $(curl --output /dev/null --silent --head --fail ${ES_HOST}:${ES_PORT}); do
    printf "${YELLOW}Waiting for Elasticsearch at ${ES_HOST}:${ES_PORT}...${NC}\n"
    sleep 0.5
done

printf "${GREEN}Found Elasticsearch at ${ES_HOST}:${ES_PORT}!${NC}\n"

#(4a) delete the previous settings if somehow there

curl -X DELETE "${ES_HOST}:${ES_PORT}/.kibana"

until curl -X GET -s ${ES_HOST}:${ES_PORT}/.kibana | jq '. | .error.type' | grep -q "index_not_found_exception" ; do
    printf "${YELLOW}Waiting for old .kibana index to be deleted...${NC}\n"
    sleep 0.5
done

#(4b) load fresh mappings and data into Elasticsearch

#load .kibana index mapping for patterns and config

curl -X PUT "${ES_HOST}:${ES_PORT}/.kibana?pretty" -H 'Content-Type: application/json' -d @/usr/share/otrebski/templates/mappings.json

#check mapping is indexed

until curl -X GET -s ${ES_HOST}:${ES_PORT}/.kibana/_mapping?pretty | jq '. | .[".kibana"].mappings | has("config")' | grep -q "true" ; do
    printf "${YELLOW}Waiting for configuration to be indexed...${NC}\n"
    sleep 0.5
done

until curl -X GET -s ${ES_HOST}:${ES_PORT}/.kibana/_mapping?pretty | jq '. | .[".kibana"].mappings | has("index-pattern")' | grep -q "true" ; do
    printf "${YELLOW}Waiting for configuration to be indexed...${NC}\n"
    sleep 0.5
done

#load .kibana index data
curl -s -XPOST "${ES_HOST}:${ES_PORT}/_bulk?pretty" --data-binary "@/tmp/otrebski_data_compact.json"

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

#(5) start kibana
#as in parent Docker image https://github.com/elastic/kibana-docker/blob/199b9ed85c2db73010dc4ac70be716cf7283d616/build/kibana
/usr/local/bin/kibana-docker
