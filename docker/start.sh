#!/bin/sh
set -e

PORT="${PORT:-8080}"

# Railway containers can occasionally retain conflicting Apache MPM symlinks.
# Force exactly one MPM (prefork) before startup.
a2dismod mpm_event >/dev/null 2>&1 || true
a2dismod mpm_worker >/dev/null 2>&1 || true
a2dismod mpm_prefork >/dev/null 2>&1 || true
a2enmod mpm_prefork >/dev/null 2>&1 || true

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
