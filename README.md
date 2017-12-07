# RIPS Zend Server Plugin

## Build

Install dependencies and create ZIP archive:

    composer install

## Development

Install dependencies:

    composer install

### Docker

Download ZendServer docker image for development: https://github.com/5square/docker-zendserver/tree/master/ZendServer-9.1
    
    git clone https://github.com/5square/docker-zendserver.git
    cd docker-zendserver
    docker build -t docker-zendserver-9.1 ZendServer-9.1

Run the server without the plugin source:

    docker run --rm -it -p 8800:80 -p 10081:10081 docker-zendserver-9.1
    
Run the server with the plugin source:

    docker run --rm -it -v $PWD:/usr/local/zend/var/plugins/rips -p 8800:80 -p 10081:10081 docker-zendserver-9.1

