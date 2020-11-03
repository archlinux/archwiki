export PORT := '8081'
export LOCAL_SETTINGS := 'LocalSettings.php'

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
	rm -f ${LOCAL_SETTINGS}
	{{PHP-DB-RUN}} php maintenance/install.php \
		--dbserver "mariadb" \
		--dbuser "root" \
		--dbpass "" \
		--installdbpass "" \
		--dbname "archwiki" \
		--scriptpath "" \
		--pass "adminpassword" \
		--confpath "/app/cache" \
		--server "http://localhost:${PORT}" \
		"ArchWiki" \
		"admin"
	{{PHP-RUN}} cat /app/cache/LocalSettings.php > ${LOCAL_SETTINGS}
	echo -e "\$wgVectorResponsive = true;\nwfLoadExtension( 'ArchLinux' );" >> ${LOCAL_SETTINGS}
	echo -e "\$wgArchHome = 'https://www.archlinux.org/';" >> ${LOCAL_SETTINGS}
	echo -e "\$wgArchNavBar = ['Start' => '#', 'Wiki' => '/'];" >> ${LOCAL_SETTINGS}
	echo -e "\$wgArchNavBarSelectedDefault = 'Wiki';" >> ${LOCAL_SETTINGS}

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
