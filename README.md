# BGIII Mod Manager Linux Helper

Small web service that aids in BG3 development in Linux.
This service is a companion to LSLIB and BGIIIMM.

## Features
- contentuid & UUID generation
- Easily Browse Mods and view most files
- Automatic DDS conversion into png for viewing
- Search function to return files with key
- Replace function to replace files found with new key
- Edit and save most files
- Edit the text files locally and refresh to see changes in service and vice versa

## How to Use
- Install and Set up [Docker](https://docs.docker.com/engine/install/)
- Edit the docker-compose.yml.example to reflect where you directories are.
- Save the edited file as docker-compose.yml
- run `bash set-up.sh` or `docker-compose up -d --build --remove-orphans`
-- docker-composer requires sudo on debian
- Naviagate to http://localhost:8080/ in a browser
- `bash destroy.sh` script will stop and delete container
- Happy Modding

