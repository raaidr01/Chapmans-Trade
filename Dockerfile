FROM php:8.4-cli

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /app
COPY . .

RUN cp config.railway.php config.php

EXPOSE 8080

CMD sh -c "php -S 0.0.0.0:${PORT:-8080}"