FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    gnupg2 \
    curl \
    wget \
    apt-transport-https \
    unixodbc-dev

RUN wget -qO /tmp/microsoft.asc https://packages.microsoft.com/keys/microsoft.asc && \
    gpg --dearmor < /tmp/microsoft.asc > /usr/share/keyrings/microsoft-prod.gpg && \
    rm /tmp/microsoft.asc

# Architecture is detected rather than fixed: development happens on Apple
# silicon (arm64) and servers are usually amd64, and a hardcoded value fails
# the driver install on whichever one it was not written for.
RUN echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/microsoft-prod.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-release.list

RUN apt-get update && ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18

RUN pecl install sqlsrv pdo_sqlsrv

RUN docker-php-ext-enable sqlsrv pdo_sqlsrv

RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev

RUN docker-php-ext-install pdo bcmath soap gd mbstring exif pcntl zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 9000
