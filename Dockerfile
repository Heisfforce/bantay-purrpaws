FROM php:8.3-cli

# Install pdo_mysql, sodium (email encryption), and curl
RUN apt-get update && apt-get install -y libcurl4-openssl-dev libsodium-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli curl sodium \
    && rm -rf /var/lib/apt/lists/*

# Copy app files
COPY . /app

WORKDIR /app

EXPOSE 8080

CMD php -S 0.0.0.0:$PORT -t /app
