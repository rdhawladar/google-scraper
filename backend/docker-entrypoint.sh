#!/bin/sh
set -e

# Start supervisor (which manages PHP-FPM, Nginx, and Laravel Queue)
exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
