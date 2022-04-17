FROM php:7.4-apache

COPY my-apache2.conf /etc/apache2/apach2.conf
RUN a2enmod rewrite
COPY src/ /var/www/html/
COPY flag.txt /flag.txt
RUN chmod 777 /var/www/html/storage/app/temp/
RUN chmod 777 /var/www/html/storage/app/uploads/
RUN chmod 555 /var/www/html/index.php
Run chmod 555 /var/www/html/.htaccess


