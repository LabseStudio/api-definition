FROM php:8.1-apache

RUN apt-get update
COPY . /var/www/html
EXPOSE 8089:80