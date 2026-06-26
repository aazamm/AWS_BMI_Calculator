FROM php:8.4-apache

# PHP extensions: pdo_pgsql + pgsql (RDS PostgreSQL), curl + mbstring (Google OAuth, text).
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      curl libpq-dev libcurl4-openssl-dev libonig-dev \
 && docker-php-ext-install pdo_pgsql pgsql curl mbstring \
 && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

# The calculator is served at /bmi/ (preserves existing URLs and the Google OAuth
# redirect URI). The build context is filtered by .dockerignore (no WordPress
# plugin, SQLite, docs, infra, etc.).
COPY --chown=www-data:www-data . /var/www/html/bmi/

# Root and the legacy WordPress calculator URL redirect to /bmi/.
RUN printf '<?php header("Location: /bmi/", true, 301); exit;\n' > /var/www/html/index.php

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD curl -fsS http://localhost/bmi/health.php || exit 1

EXPOSE 80
