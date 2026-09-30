# syntax=docker/dockerfile:1
FROM php:8.3-cli-bookworm
RUN --mount=type=secret,id=proxy_ca,target=/usr/local/share/ca-certificates/session-proxy.crt,required=true \
    update-ca-certificates && apt-get update && apt-get install -y --no-install-recommends dovecot-imapd openssl python3 ca-certificates && rm -rf /var/lib/apt/lists/*
RUN update-ca-certificates --fresh
COPY dovecot.conf /etc/dovecot/dovecot.conf
COPY server.py /server.py
CMD ["python3", "/server.py"]
