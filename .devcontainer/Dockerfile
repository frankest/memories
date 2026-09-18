# Use the official Nextcloud image as the base image
FROM nextcloud:34-apache

# Install Node.js 24 and npm
# Also install common development tools
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - && \
    apt-get install -y nodejs && \
    apt-get install -y sudo git wget make unzip vim ffmpeg && \
    rm -rf /var/lib/apt/lists/*

# Install PHP Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer  && \
    php -r "unlink('composer-setup.php');"

RUN pecl install xdebug && docker-php-ext-enable xdebug
COPY xdebug.ini /usr/local/etc/php/conf.d/99-xdebug-settings.ini
