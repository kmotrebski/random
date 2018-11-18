#!/usr/bin/env bash

RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

#ps ax | grep ssh | grep -v grep

#http://www.g-loaded.eu/2006/11/24/auto-closing-ssh-tunnels/ and lot of further research

while true
do

    INSTANCE_IP=`terraform output -json -state=infra/terraform.tfstate | jq --raw-output '.aws_ip.value'`
    printf "${GREEN}Going to connect with IP=${INSTANCE_IP}...${NC}\n"

    ssh -N -i ~/.ssh/id_rsa \
        -o ServerAliveInterval=1 \
        -o ServerAliveCountMax=2 \
        -o ExitOnForwardFailure=yes \
        -o StrictHostKeyChecking=no \
        -o TCPKeepAlive=yes \
        -L 3000:localhost:3000 \
        -L 5601:localhost:5601 \
        user@${INSTANCE_IP}

    printf "BROKEN \n"
    sleep 1
done
