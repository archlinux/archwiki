/*
 * Do not delete interwiki links in order to avoid breaking existing instances
 * Interlinks to dead wikis should be simply removed from the articles
 */

REPLACE INTO
	interwiki (iw_prefix, iw_url, iw_local, iw_trans)
VALUES
	('de', 'https://wiki.archlinux.de/title/$1', 1, 0),
	('en', 'https://wiki.archlinux.org/index.php/$1', 1, 0),
	('es', 'http://www.archlinux-es.org/wiki/index.php?title=$1', 1, 0),
	('fa', 'http://wiki.archlinux.ir/index.php/$1', 1, 0),
	('fi', 'http://www.archlinux.fi/wiki/$1', 1, 0),
	('fr', 'http://wiki.archlinux.fr/$1', 1, 0),
	('pl', 'http://wiki.archlinux.pl/$1', 1, 0),
	('pt-br', 'http://wiki.archlinux-br.org/$1', 1, 0),
	('ro', 'http://wiki.archlinux.ro/index.php/$1', 1, 0),
	('sv', 'http://wiki.archlinux.se/index.php?title=$1', 1, 0),
	('tr', 'http://archtr.org/wiki/index.php?title=$1', 1, 0),
	('uk', 'http://wiki.archlinux.org.ua/$1', 1, 0);
