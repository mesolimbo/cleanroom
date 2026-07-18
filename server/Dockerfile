FROM php:8.3-apache

# Honor the same .htaccess we deploy to Dreamhost
RUN sed -i 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

COPY index.php .htaccess favicon.ico favicon.gif logo.svg /var/www/html/
