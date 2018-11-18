#!/usr/bin/env bash

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m'

docker-compose -f images/grafana/docker-compose-test.yaml -p grafana_test up -d

#wait till elasticsearch is ready
until $(curl --output /dev/null --silent --head --fail localhost:9200); do
    printf "${YELLOW}Waiting for ES...${NC}\n"
    sleep 0.5
done

printf "${GREEN}Waiting for ES...${NC}\n"

#add index template

DATE=`date --utc '+%Y-%m-%d %H:%M:%S'`
DATE="${DATE}.0000"

INDEX_SUFFIX=`date --utc '+%Y-%m-%d'`
INDEX="app_logs${INDEX_SUFFIX}"
TYPE="app_logs"

curl -X PUT "localhost:9200/_template/template_app_logs_v1?pretty" -H 'Content-Type: application/json' -d'
{
  "template": "app_logs*",
  "settings": {
    "number_of_shards": 1,
    "number_of_replicas": 0,
    "index.mapper.dynamic": false
  },
  "mappings": {
    "app_logs": {
      "dynamic": "strict",
      "properties": {
        "severity": {
          "type": "keyword"
        },
        "message": {
          "type": "text"
        },
        "timestamp": {
          "type": "date",
          "format": "yyyy-MM-dd HH:mm:ss.SSSSSS"
        }
      }
    }
  }
}
'

sleep 2

#index few documents

curl -X POST "localhost:9200/${INDEX}/${TYPE}?pretty" -H 'Content-Type: application/json' -d"
{
  \"severity\": \"My second blog entry\",
  \"message\":  \"Still trying this out...\",
  \"timestamp\":  \"${DATE}\"
}
"

curl -X POST "localhost:9200/${INDEX}/${TYPE}?pretty" -H 'Content-Type: application/json' -d"
{
  \"severity\": \"My second blog entry\",
  \"message\":  \"Still trying this out...\",
  \"timestamp\":  \"${DATE}\"
}
"

curl -X POST "localhost:9200/${INDEX}/${TYPE}?pretty" -H 'Content-Type: application/json' -d"
{
  \"severity\": \"My second blog entry\",
  \"message\":  \"Still trying this out...\",
  \"timestamp\":  \"${DATE}\"
}
"

curl -X POST "localhost:9200/${INDEX}/${TYPE}?pretty" -H 'Content-Type: application/json' -d"
{
  \"severity\": \"My second blog entry\",
  \"message\":  \"Still trying this out...\",
  \"timestamp\":  \"${DATE}\"
}
"

# test that Dashboard is present

HTTP_CODE=`curl -X GET -H "Content-Type: application/json" -s -o /dev/null -w "%{http_code}" http://localhost:3000/api/dashboards/uid/CrzE5pniz`

docker-compose -f images/grafana/docker-compose-test.yaml -p grafana_test down

if [ "$HTTP_CODE" == "200" ]; then
    printf "${GREEN}Tests passed!${NC}"
    exit 0
else
    printf "${RED}Tests passed!${NC}"
    exit 2
fi
