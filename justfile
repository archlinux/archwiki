export PORT := '9091'

export UID := `id -u`
export GID := `id -g`

COMPOSE := 'docker compose -f docker/app.yml -p archwiki'
COMPOSE-RUN := COMPOSE + ' run --rm'
PHP-DB-RUN := COMPOSE-RUN + ' app'
PHP-RUN := COMPOSE-RUN + ' --no-deps app'
MARIADB-RUN := COMPOSE-RUN + ' -T --no-deps mariadb'

default:
	just --list

# Installs MediaWiki and creates LocalSettings.php
init: start
	rm -f LocalSettings.php
	{{PHP-DB-RUN}} php maintenance/run.php install \
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
	echo -e "\$wgVectorResponsive = true;\n\$wgVectorDefaultSkinVersion = '2';\nwfLoadExtension( 'ArchLinux' );" >> LocalSettings.php
	sed -E 's/^(\$wgDefaultSkin\s*=\s*).+/\1"vector-2022";/g' -i LocalSettings.php
	echo -e "\$wgArchHome = 'https://www.archlinux.org/';" >> LocalSettings.php
	echo -e "\$wgArchNavBar = ['Start' => '#', 'Wiki' => '/'];" >> LocalSettings.php
	echo -e "\$wgArchNavBarSelectedDefault = 'Wiki';" >> LocalSettings.php

# Load a (gzipped) database backup for local testing
import-db-dump file name='archwiki': start
	{{MARIADB-RUN}} mariadb-admin -uroot -hmariadb --skip-ssl drop -f {{name}} || true
	{{MARIADB-RUN}} mariadb-admin -uroot -hmariadb --skip-ssl create {{name}}
	zcat {{file}} | {{MARIADB-RUN}} mariadb -uroot -hmariadb --skip-ssl {{name}}
	{{PHP-RUN}} php maintenance/run.php update --quick

start:
	{{COMPOSE}} up -d
	{{MARIADB-RUN}} mariadb-admin -uroot -hmariadb --skip-ssl --wait=10 ping
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

update version:
	#!/usr/bin/env bash
	set -euo pipefail

	TMPDIR=$(mktemp -d)

	version={{version}}
	branch=${version%.*}

	pushd $TMPDIR >/dev/null
	wget https://releases.wikimedia.org/mediawiki/${branch}/mediawiki-{{version}}.tar.gz{,.sig}
	gpg --verify-files mediawiki-{{version}}.tar.gz.sig
	popd >/dev/null

	shopt -s extglob
	rm -rf !("justfile"|"favicon.ico"|".gitmodules"|".gitignore"|"sitemaps"|".git"|"docker"|".idea"|"extensions")
	pushd extensions >/dev/null
	rm -rf !("ArchLinux"|"BounceHandler"|"CheckUser"|"CodeMirror"|"DarkMode"|"FlarumAuth"|"Lockdown"|"TitleKey"|"UserMerge")
	popd >/dev/null
	shopt -u extglob

	tar -xz --strip-components=1 -f $TMPDIR/mediawiki-{{version}}.tar.gz

	rm -rf $TMPDIR

	git submodule update
	git submodule foreach git fetch
	git submodule foreach git checkout REL${branch/./_}
	git submodule foreach git pull
	for module in $(git submodule foreach --quiet 'echo $name'); do
		git submodule set-branch --branch REL${branch/./_} "${module}"
	done

	git add -u
	git add .
	git commit -am"Update to MediaWiki {{version}}"
