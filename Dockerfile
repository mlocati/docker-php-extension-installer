FROM bash AS build

COPY install-php-extensions /tmp/install-php-extensions
RUN chmod +x /tmp/install-php-extensions

FROM scratch

LABEL org.opencontainers.image.source="https://github.com/mlocati/docker-php-extension-installer" \
      org.opencontainers.image.licenses="MIT"

COPY --from=build /tmp/install-php-extensions /usr/bin/install-php-extensions
