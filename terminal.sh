#!/usr/bin/env bash

INSTANCE_IP=`terraform output -json -state=infra/terraform.tfstate | jq --raw-output '.aws_ip.value'`
printf "IP: ${INSTANCE_IP}\n"
printf "Connecting...\n"

ssh user@${INSTANCE_IP}
