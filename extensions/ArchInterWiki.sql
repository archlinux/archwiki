/*
 * Before deleting interlanguage links, remove them from the articles
 *
 * In order to find all the interlanguage links of a particular language you
 *   need to do an API search, e.g.
 *   https://wiki.archlinux.org/api.php?action=query&list=langbacklinks&lbllimit=500&lblprop=lltitle&lbllang=de
 * That example uses German ('de'), but for the other languages it's enough to
 *   change the value of 'lbllang' to the needed language tag.
 * Note that API queries are always limited, so if a language has more than 500
 *   backlinks it will be necessary to continue the search adding the
 *   'lblcontinue' attribute that appears at the bottom of the list to the
 *   query string.
 * Also note that such a query does not find (all) interwiki redirects, if
 *   present: a search like
 *   https://wiki.archlinux.org/index.php?title=Special%3ASearch&profile=advanced&limit=500&offset=0&search=%22redirect%20%5B%5Bde%3A%22&fulltext=Search&ns0=1&ns1=1&ns2=1&ns3=1&ns4=1&ns5=1&ns6=1&ns7=1&ns8=1&ns9=1&ns10=1&ns11=1&ns12=1&ns13=1&ns14=1&ns15=1&redirs=1&profile=advanced
 *   should do the job instead.
 */

DELETE FROM interwiki WHERE iw_prefix='pt-br';

REPLACE INTO
	interwiki (iw_prefix, iw_url, iw_local, iw_trans)
VALUES
	('bg', 'https://wiki.archlinux.org/index.php/$1_(%D0%91%D1%8A%D0%BB%D0%B3%D0%B0%D1%80%D1%81%D0%BA%D0%B8)', 1, 0),
	('cs', 'https://wiki.archlinux.org/index.php/$1_(%C4%8Cesky)', 1, 0),
	('da', 'https://wiki.archlinux.org/index.php/$1_(Dansk)', 1, 0),
	('de', 'https://wiki.archlinux.de/title/$1', 1, 0),
	('el', 'https://wiki.archlinux.org/index.php/$1_(%CE%95%CE%BB%CE%BB%CE%B7%CE%BD%CE%B9%CE%BA%CE%AC)', 1, 0),
	('en', 'https://wiki.archlinux.org/index.php/$1', 1, 0),
	('es', 'https://wiki.archlinux.org/index.php/$1_(Espa%C3%B1ol)', 1, 0),
	('fa', 'http://wiki.archlinux.ir/index.php/$1', 1, 0),
	('fi', 'http://www.archlinux.fi/wiki/$1', 1, 0),
	('fr', 'http://wiki.archlinux.fr/$1', 1, 0),
	('he', 'https://wiki.archlinux.org/index.php/$1_(%D7%A2%D7%91%D7%A8%D7%99%D7%AA)', 1, 0),
	('hr', 'https://wiki.archlinux.org/index.php/$1_(Hrvatski)', 1, 0),
	('hu', 'https://wiki.archlinux.org/index.php/$1_(Magyar)', 1, 0),
	('id', 'https://wiki.archlinux.org/index.php/$1_(Indonesia)', 1, 0),
	('it', 'https://wiki.archlinux.org/index.php/$1_(Italiano)', 1, 0),
	('ja', 'https://wiki.archlinux.org/index.php/$1_(%E6%97%A5%E6%9C%AC%E8%AA%9E)', 1, 0),
	('ko', 'https://wiki.archlinux.org/index.php/$1_(%ED%95%9C%EA%B5%AD%EC%96%B4)', 1, 0),
	('lt', 'https://wiki.archlinux.org/index.php/$1_(Lietuvi%C5%A1kai)', 1, 0),
	('nl', 'https://wiki.archlinux.org/index.php/$1_(Nederlands)', 1, 0),
	('pl', 'https://wiki.archlinux.org/index.php/$1_(Polski)', 1, 0),
	('pt', 'https://wiki.archlinux.org/index.php/$1_(Portugu%C3%AAs)', 1, 0),
	('ro', 'http://wiki.archlinux.ro/index.php/$1', 1, 0),
	('ru', 'https://wiki.archlinux.org/index.php/$1_(%D0%A0%D1%83%D1%81%D1%81%D0%BA%D0%B8%D0%B9)', 1, 0),
	('sk', 'https://wiki.archlinux.org/index.php/$1_(Slovensk%C3%BD)', 1, 0),
	('sr', 'https://wiki.archlinux.org/index.php/$1_(%D0%A1%D1%80%D0%BF%D1%81%D0%BA%D0%B8)', 1, 0),
	('sv', 'http://wiki.archlinux.se/index.php?title=$1', 1, 0),
	('th', 'https://wiki.archlinux.org/index.php/$1_(%E0%B9%84%E0%B8%97%E0%B8%A2)', 1, 0),
	('tr', 'http://archtr.org/wiki/index.php?title=$1', 1, 0),
	('uk', 'https://wiki.archlinux.org/index.php/$1_(%D0%A3%D0%BA%D1%80%D0%B0%D1%97%D0%BD%D1%81%D1%8C%D0%BA%D0%B0)', 1, 0),
	('zh-cn', 'https://wiki.archlinux.org/index.php/$1_(%E7%AE%80%E4%BD%93%E4%B8%AD%E6%96%87)', 1, 0),
	('zh-tw', 'https://wiki.archlinux.org/index.php/$1_(%E6%AD%A3%E9%AB%94%E4%B8%AD%E6%96%87)', 1, 0);
