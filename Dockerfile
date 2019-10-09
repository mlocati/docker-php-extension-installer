FROM bash AS build

COPY install-php-extensions /tmp/install-php-extensions
RUN chmod +x /tmp/install-php-extensions

FROM scratch

COPY --from=build /tmp/install-php-extensions /usr/bin/install-php-extensions
