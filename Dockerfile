FROM php:8.4-cli

RUN docker-php-ext-install pdo pdo_mysql

WORKDIR /app
COPY . .
RUN cp config.railway.php config.php

# Prevent PHP execution in uploads folder
RUN mkdir -p assets/uploads/listings && \
    echo "<?php http_response_code(403); exit; ?>" > assets/uploads/listings/index.php && \
    chmod 755 assets/uploads/listings

EXPOSE 8080
CMD sh -c 'php -S 0.0.0.0:$PORT'