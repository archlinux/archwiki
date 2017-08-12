FROM archlinux/base

RUN pacman -Syu --noconfirm systemd mariadb
RUN mysql_install_db --user=mysql --basedir=/usr --datadir=/var/lib/mysql
