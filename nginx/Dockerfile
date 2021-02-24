FROM nginx:stable-alpine
LABEL maintainer="jen.k.mcquade@gmail.com"

RUN mkdir -p /var/log/nginx && \
    apk update && \
    apk upgrade && \
    apk add bash && \
    apk add vim
    
COPY ./nginx.conf /etc/nginx
COPY ./slug.conf /etc/nginx/conf.d

EXPOSE 9082
