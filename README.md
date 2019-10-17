# RIPS Zend Server Plugin

[![Build Status](https://travis-ci.org/rips/zend-server.svg?branch=master)](https://travis-ci.org/rips/zend-server)

[Documentation](https://kb.ripstech.com/display/DOC/Zend+Server)

## Build

Install dependencies and create ZIP archive:

    composer install

## Development

Install dependencies:

    composer install

### Docker
Run the docker compose script in the `scripts/` director as
```shell script
sudo docker-copose -f scripts/docker-compose.yml up
```

The zend server container will be attatched to the devenv network, therefore the devenv must be running.
This directory will also be mounted into the plugins directory of zend.
The zend web root (`/var/www/html`) is also available on the host machine via `/tmp/zend`.
