#!/bin/bash

echo -en "Setting up configuration"
sudo sysctl vm.max_map_count=262144 > /dev/null 2>&1
echo -e "[ \e[32mConfig OK\e[0m ]"

echo -e "Building Docker"
sudo docker-compose up -d --build --remove-orphans || exit 1
echo -e "[ \e[32mDocker Built\e[0m ]"
