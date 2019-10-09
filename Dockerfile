FROM bash

COPY install-php-extensions /usr/bin/install-php-extensions
RUN chmod +x /usr/bin/install-php-extensions
