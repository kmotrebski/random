#!/usr/bin/env bash

curl -XGET -s localhost:24444/api/config.reload | jq '.'
