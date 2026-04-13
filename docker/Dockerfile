FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql opcache \
	&& a2enmod rewrite headers

COPY docker/apache-site.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
