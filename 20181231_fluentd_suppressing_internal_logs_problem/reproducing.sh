#!/usr/bin/env bash

#create all directories
mkdir -p logs/fluentd
mkdir -p logs/sth_awesome
chmod 777 logs/fluentd
chmod 777 logs/sth_awesome

#close previous attempt and clear files
docker-compose -p test down
rm -rf logs/fluentd/*
rm -rf logs/sth_awesome/*

#start new attempt
docker-compose -p test up -d

#wait till container is up
sleep 5

#stream log that will cause an "pattern not match message" error
echo 'awesome:123456' | netcat localhost 5170

#wait
sleep 3

#errors are in docker logs are present
printf "\n\nDOCKER LOGS:\n"
docker logs test_fluentd_1 | grep "pattern not match message"

#errors in file are missing
printf "\n\nFILE LOGS:\n"
cat logs/fluentd/*.log | grep "pattern not match message"
