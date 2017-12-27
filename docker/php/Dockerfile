FROM archlinux/base

RUN pacman -Syu --noconfirm --needed systemd
RUN pacman -Syu --noconfirm --needed php php-intl php-apcu php-gd imagemagick git sudo diffutils
ADD etc/ /etc
