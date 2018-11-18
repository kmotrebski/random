#!/usr/bin/env bash

curl -XGET localhost:9200/.kibana/index-pattern/_search?pretty | jq '.hits.hits[0]._source.fields | fromjson'