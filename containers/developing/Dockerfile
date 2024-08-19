FROM debian:11-slim

USER root

# Get Debian up-to-date
RUN apt-get update -qq \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y git \
    mariadb-client wget curl \
    ca-certificates lsb-release apt-transport-https gnupg unzip

# Install 3rd party PHP packages
RUN echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" | tee -a /etc/apt/sources.list.d/php.list

RUN wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg

RUN  apt-get update -qq \
    && DEBIAN_FRONTEND=noninteractive apt-get install --no-install-recommends -y php8.2 php8.2-common php8.2-cli php8.2-fpm \
    php8.2-mysql php8.2-curl php8.2-xml php8.2-mbstring \
    php8.2-intl php8.2-redis php8.2-zip php8.2-xdebug

COPY xdebug.ini /etc/php/8.2/fpm/conf.d/20-xdebug.ini

# RUN echo "xdebug.mode=coverage,debug" >> /etc/php/8.2/cli/conf.d/20-xdebug.ini

# Make the default directory be where your code is.
WORKDIR /var/app

CMD tail -f README.md

# CMD sh /var/app/containers/php_fpm/entrypoint.sh