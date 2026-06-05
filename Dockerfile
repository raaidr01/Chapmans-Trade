FROM php:8.4-cli

RUN docker-php-ext-install pdo pdo_mysql

RUN apt-get update && apt-get install -y libcurl4-openssl-dev && \
    docker-php-ext-install curl && \
    rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .
RUN cp config.railway.php config.php

CMD php -S 0.0.0.0:$PORT