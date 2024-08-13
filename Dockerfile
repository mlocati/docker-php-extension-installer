FROM scratch

LABEL org.opencontainers.image.source="https://github.com/mlocati/docker-php-extension-installer"
LABEL org.opencontainers.image.licenses="MIT"

COPY --chmod=755 install-php-extensions /usr/bin/install-php-extensions
