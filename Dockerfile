FROM php:8.2-apache

RUN docker-php-ext-install mysqli \
    && a2dismod mpm_event mpm_worker \
    && a2enmod mpm_prefork rewrite

WORKDIR /var/www/html

COPY . /var/www/html
COPY docker/start.sh /usr/local/bin/start.sh

RUN chmod +x /usr/local/bin/start.sh \
    && mkdir -p /var/www/html/assets/uploads/products /var/www/html/assets/uploads/id_images \
    && chown -R www-data:www-data /var/www/html/assets/uploads

EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]
