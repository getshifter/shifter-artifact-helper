FROM getshifter/shifter_local:latest

ENV DOCKERIZE_VERSION v0.6.1
RUN wget https://github.com/jwilder/dockerize/releases/download/$DOCKERIZE_VERSION/dockerize-linux-amd64-$DOCKERIZE_VERSION.tar.gz \
    && tar -C /usr/local/bin -xzvf dockerize-linux-amd64-$DOCKERIZE_VERSION.tar.gz \
    && rm dockerize-linux-amd64-$DOCKERIZE_VERSION.tar.gz

# ADD volume/app /var/www/html/web/wp/wp-content
# ADD volume/dump /mnt/dump
ADD cont-init.d/02-importdb /etc/cont-init.d/02-importdb
ADD fix-attrs.d/mntdir /etc/fix-attrs.d/mntdir
ADD scripts /scripts
