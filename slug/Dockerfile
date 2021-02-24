FROM php:fpm-alpine
LABEL maintainer="jen.k.mcquade@gmail.com"

ARG slug_env

ENV SLUG_ENV=$slug_env
ENV SLUG_CONFIG="/slug.json"
ENV SLUG_EXEC_ROOT="/usr/local/bin/slug"
ENV PYTHONUNBUFFERED=1

RUN if [ ${SLUG_ENV} == '' ]; \
        then ${SLUG_ENV} = 'development'; \
    fi

COPY entrypoint /usr/local/bin
RUN chmod +x /usr/local/bin/entrypoint

RUN mkdir -p /usr/local/bin/slug-scripts && \
    apk add --update --no-cache \
    g++ \
    make \
    bash \
    libzip-dev \
    php7-dev \
    python3 \
    ruby ruby-json ruby-rdoc ruby-irb ruby-rake ruby-io-console ruby-bigdecimal ruby-json ruby-bundler \
    libstdc++ tzdata ca-certificates \
    nodejs 

RUN ln -sf python3 /usr/bin/python python3 \
    && python3 -m ensurepip && python3 -m pip install --upgrade pip \
    && pip install requests

# Install PHP extensions
RUN docker-php-source extract \
    && docker-php-ext-install zip \
    && docker-php-ext-enable zip \
    && if [ ${SLUG_ENV} == 'development' ]; \ 
        then docker-php-source extract \
        && pecl install xdebug \
        && docker-php-ext-enable xdebug; \
    fi \
    && docker-php-source delete  

# Custom variables for PHP        
RUN echo "error_log=/dev/stderr" >> /usr/local/etc/php/conf.d/slug.ini \
    && echo "error_prepend_string={\"SLUG\": " >> /usr/local/etc/php/conf.d/slug.ini \
    && echo "error_append_string={}" >> /usr/local/etc/php/conf.d/slug.ini \
    && echo "file_uploads=Off" >> /usr/local/etc/php/conf.d/slug.ini \
    && echo "log_errors=On" >> /usr/local/etc/php/conf.d/slug.ini \   
    && echo "output_encoding='UTF-8'" >> /usr/local/etc/php/conf.d/slug.ini \
    && echo "output_buffering=On" >> /usr/local/etc/php/conf.d/slug.ini \
    && echo "session.cookie_secure=On" >> /usr/local/etc/php/conf.d/slug.ini \
    && echo "session.name=SLUG" >> /usr/local/etc/php/conf.d/slug.ini \
    && echo "date_default_timezone_set='UTC'" >> /usr/local/etc/php/conf.d/slug.ini \
    && rm -rf /tmp/*

# Install composer
RUN if [ ${SLUG_ENV} == 'development' ]; \
        then curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer; \
    fi

COPY ./sample/ /usr/local/bin/slug-scripts/
COPY ./slug.json /
RUN chmod 444 /slug.json
RUN chmod -R 555 /usr/local/bin/slug-scripts

CMD ["entrypoint"]

WORKDIR /var/www/html

EXPOSE 9000
EXPOSE 9011
