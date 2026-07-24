FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libzip-dev libicu-dev zlib1g-dev libonig-dev unzip git \
        msmtp msmtp-mta ca-certificates \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd mysqli pdo pdo_mysql zip mbstring intl opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite headers

# msmtp: PHP mail() -> MailHog (개발) / 외부 SMTP (프로덕션: SMTP_ env 설정)
RUN echo "account default" > /etc/msmtprc \
 && echo "host mailhog"   >> /etc/msmtprc \
 && echo "port 1025"      >> /etc/msmtprc \
 && echo "from no-reply@joongna.local" >> /etc/msmtprc \
 && echo "auth off"       >> /etc/msmtprc \
 && echo "tls off"        >> /etc/msmtprc \
 && echo "syslog on"      >> /etc/msmtprc \
 && chmod 644 /etc/msmtprc

# 프로덕션 이메일 설정 스크립트 (컨테이너 진입 후 실행: docker exec joongna_apache sh /setup-smtp.sh)
RUN echo '#!/bin/sh' > /setup-smtp.sh \
 && echo 'cat > /etc/msmtprc <<EOF' >> /setup-smtp.sh \
 && echo 'account default' >> /setup-smtp.sh \
 && echo 'host ${SMTP_HOST}' >> /setup-smtp.sh \
 && echo 'port ${SMTP_PORT:-587}' >> /setup-smtp.sh \
 && echo 'from ${SMTP_FROM}' >> /setup-smtp.sh \
 && echo 'auth on' >> /setup-smtp.sh \
 && echo 'user ${SMTP_USER}' >> /setup-smtp.sh \
 && echo 'password ${SMTP_PASS}' >> /setup-smtp.sh \
 && echo 'tls on' >> /setup-smtp.sh \
 && echo 'tls_starttls on' >> /setup-smtp.sh \
 && echo 'syslog on' >> /setup-smtp.sh \
 && echo 'EOF' >> /setup-smtp.sh \
 && echo 'echo "SMTP configured."' >> /setup-smtp.sh \
 && chmod +x /setup-smtp.sh

# PHP sendmail_path -> msmtp
RUN echo "sendmail_path = /usr/bin/msmtp -t" >> /usr/local/etc/php/conf.d/zz-joongna.ini

WORKDIR /var/www/html
# uploads 폴더를 www-data 권한으로 미리 생성 (볼륨 마운트 시에도 작동)
RUN mkdir -p /var/www/html/uploads/img /var/www/html/uploads/vid && \
    chown -R www-data:www-data /var/www/html/uploads && \
    chmod -R 777 /var/www/html/uploads
EXPOSE 80