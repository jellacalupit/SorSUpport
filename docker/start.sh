#!/usr/bin/env bash
# Starts the application container: prepares storage, updates the database, then runs the web
# server together with the two background loops that deliver email.

cd /var/www/html || exit 1

a2dismod mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork rewrite

# Private ticket attachments live inside the uploads volume (the only persistent storage), in a
# folder the web server refuses to serve, linked to where Laravel's private disk expects them.
mkdir -p storage/app/public/.private
rm -rf storage/app/private
ln -sfn /var/www/html/storage/app/public/.private storage/app/private
php artisan storage:link

php artisan migrate --force || exit 1

chown -R www-data:www-data storage bootstrap/cache

# Queue worker: account emails (verification, account updates) are queued.
su -s /bin/bash www-data -c 'while true; do /usr/local/bin/php artisan queue:work --tries=3 --backoff=30 --sleep=3 --max-time=3600; sleep 5; done' &

# Scheduler: ticket emails are sent by a command that runs every minute.
su -s /bin/bash www-data -c 'while true; do /usr/local/bin/php artisan schedule:run; sleep 60; done' &

# Report in the deployment log whether the mail server can be reached.
(timeout 45 /usr/local/bin/php artisan mail:check || true) &

exec apache2-foreground
