# Use official PHP with Apache image
FROM php:8.2-apache

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Copy project files to Apache document root
COPY . /var/www/html/

# Make sure Apache listens on Railway's port
ARG PORT
RUN sed -i "s/80/${PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Expose the dynamic PORT
EXPOSE ${PORT}

# Start Apache in foreground
CMD ["apache2-foreground"]
