export PORT := '8081'

export UID := `id -u`
export GID := `id -g`

COMPOSE := 'docker-compose -f docker/app.yml -p archwiki'
COMPOSE-RUN := COMPOSE + ' run --rm'
PHP-DB-RUN := COMPOSE-RUN + ' app'
PHP-RUN := COMPOSE-RUN + ' --no-deps app'
MARIADB-RUN := COMPOSE-RUN + ' --no-deps mariadb'

default:
	just --list

# Installs MediaWiki and creates LocalSettings.php
init: start
	{{PHP-DB-RUN}} php maintenance/install.php \
		--dbserver "mariadb" \
		--dbuser "root" \
		--dbpass "" \
		--installdbpass "" \
		--dbname "archwiki" \
		--scriptpath "" \
		--pass "adminpassword" \
		--server "http://localhost:${PORT}" \
		"ArchWiki" \
		"admin"
	echo -e "\$wgVectorResponsive = true;\nwfLoadExtension( 'ArchLinux' );" >> LocalSettings.php
	echo -e "\$wgArchHome = 'https://www.archlinux.org/';" >> LocalSettings.php
	echo -e "\$wgArchNavBar = ['Start' => '#', 'Wiki' => '/'];" >> LocalSettings.php
	echo -e "\$wgArchNavBarSelectedDefault = 'Wiki';" >> LocalSettings.php

# Load a (gzipped) database backup for local testing
import-db-dump file name='archwiki': start
	{{MARIADB-RUN}} mysqladmin -uroot -hmariadb drop -f {{name}} || true
	{{MARIADB-RUN}} mysqladmin -uroot -hmariadb create {{name}}
	zcat {{file}} | {{MARIADB-RUN}} mysql -uroot -hmariadb {{name}}
	{{PHP-RUN}} php maintenance/update.php --quick

start:
	{{COMPOSE}} up -d
	{{MARIADB-RUN}} mysqladmin -uroot -hmariadb --wait=10 ping
	@echo URL: http://localhost:${PORT}

stop:
	{{COMPOSE}} stop

clean:
	{{COMPOSE}} down -v
	git clean -fdqx -e .idea

rebuild: clean
	{{COMPOSE}} build --pull --parallel
	just init
	just stop

compose *args:
	{{COMPOSE}} {{args}}

compose-run *args:
	{{COMPOSE-RUN}} {{args}}

php *args='-h':
	{{PHP-RUN}} php {{args}}

# vim: set ft=make :
