FROM wordpress:php7.1-apache
COPY --chown=www-data:www-data . /var/www/html

# Modify directory and file permission
RUN find /var/www/ -type d -exec chmod 0755 {} \;
RUN find /var/www/ -type f -exec chmod 644 {} \;
