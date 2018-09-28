#!/bin/bash

cd /var/www/html/web/wp || exit 1
sudo -u www-data /usr/local/bin/wp db import /mnt/dump/wp.sql
