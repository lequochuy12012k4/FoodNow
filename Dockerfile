FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Thử cài từng extension
RUN docker-php-ext-install mysqli
RUN echo "mysqli installed"

RUN docker-php-ext-install pdo_mysql
RUN echo "pdo_mysql installed"

RUN docker-php-ext-install opcache
RUN echo "opcache installed"

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp
RUN docker-php-ext-install gd
RUN echo "gd installed"

# Đây là dòng lệnh gây lỗi
RUN docker-php-ext-install mbstring
RUN echo "mbstring installed"

# Nếu bạn cần zip
# RUN docker-php-ext-install zip
# RUN echo "zip installed"

RUN a2enmod rewrite
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80
CMD ["apache2-foreground"]