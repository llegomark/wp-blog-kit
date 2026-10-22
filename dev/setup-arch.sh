#!/bin/bash
# dev/setup-arch.sh: one-time, root-level setup for local WordPress development on Arch Linux.
# Native packages only, no Docker. Read it, then run it yourself:
#
#     sudo bash ~/Documents/wp-blog-kit/dev/setup-arch.sh
#
# What it does:
#   1. Installs PHP 8.5 (the same version as the servers), Composer, MariaDB, and the PHP extensions
#      WordPress uses.
#   2. Switches those PHP extensions on (Arch ships them switched off).
#   3. Sets MariaDB up for local use only: no network port, login through the Unix socket.
#   4. Creates a database account for your Linux user. It has no password because only your Linux
#      account can use it, and it may only create and use databases whose names start with "wp_".
# Running it again is safe.
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Run it with sudo." >&2; exit 1; }
DEV_USER=${SUDO_USER:-}
if [ -z "$DEV_USER" ] || [ "$DEV_USER" = root ]; then
  echo "Run it with sudo from your normal account, not as root." >&2
  exit 1
fi

echo "==> Packages"
pacman -S --needed --noconfirm php php-gd php-sqlite php-imagick composer mariadb

echo "==> PHP extensions"
INI=/etc/php/conf.d/50-wordpress-dev.ini
rm -f "$INI"
loaded=$(php -m | tr '[:upper:]' '[:lower:]')
{
  echo "; Written by wp-blog-kit/dev/setup-arch.sh: extensions and limits for WordPress development."
  for ext in bcmath exif gd iconv intl mysqli pdo_mysql pdo_sqlite sodium zip imagick; do
    if ! grep -qx "$ext" <<<"$loaded" && [ -f "/usr/lib/php/modules/$ext.so" ]; then
      echo "extension=$ext"
    fi
  done
  echo "memory_limit = 512M"
  echo "upload_max_filesize = 64M"
  echo "post_max_size = 64M"
} > "$INI"
php -m | tr '[:upper:]' '[:lower:]' | grep -qx mysqli || { echo "mysqli did not load; check $INI" >&2; exit 1; }

echo "==> MariaDB (local only)"
if [ ! -d /var/lib/mysql/mysql ]; then
  mariadb-install-db --user=mysql --basedir=/usr --datadir=/var/lib/mysql >/dev/null
fi
cat > /etc/my.cnf.d/zz-wp-dev.cnf <<'EOF'
# Written by wp-blog-kit/dev/setup-arch.sh. Local development only.
[mysqld]
# No TCP port at all: only programs on this laptop can connect, through the socket.
skip-networking
character-set-server = utf8mb4
collation-server     = utf8mb4_unicode_ci
EOF
systemctl enable --now mariadb
systemctl restart mariadb
mariadb -e "CREATE USER IF NOT EXISTS '${DEV_USER}'@'localhost' IDENTIFIED VIA unix_socket;
            GRANT ALL PRIVILEGES ON \`wp\_%\`.* TO '${DEV_USER}'@'localhost';"

echo
echo "Done:"
php -v | head -n 1
sudo -u "$DEV_USER" composer --version 2>/dev/null | head -n 1 || true
mariadb --version
echo "Database account: ${DEV_USER} (socket login, databases named wp_*)."
echo "To stop MariaDB from starting at boot: sudo systemctl disable --now mariadb"
