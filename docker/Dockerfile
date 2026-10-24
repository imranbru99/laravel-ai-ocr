ARG PHP_VERSION=8.4
FROM docker.io/library/composer:2 AS composer
FROM docker.io/library/php:${PHP_VERSION}-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libicu-dev libonig-dev libxml2-dev \
    poppler-utils tesseract-ocr tesseract-ocr-eng tesseract-ocr-ben \
    && docker-php-ext-install zip intl mbstring dom bcmath \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/bin/composer
WORKDIR /package
COPY . /package
CMD ["sh", "-c", "composer install --no-interaction && composer test"]
