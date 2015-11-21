/*
 * This script is intended to populate the interwiki table with entries useful
 * for ArchWiki.
 *
 * Before deleting interwiki links, remove them from the articles.
 *
 * In order to find all the interlanguage links of a particular language, you
 * need to do an API search, e.g.
 *     https://wiki.archlinux.org/api.php?action=query&list=langbacklinks&lbllimit=max&lblprop=lltitle&lbllang=de
 * This example uses German ('de'), but for the other languages it is enough to
 * change the value of 'lbllang' to the needed language tag.
 *
 * Interwiki links using a specific prefix can be found similarly, for example:
 *     https://wiki.archlinux.org/api.php?action=query&list=iwbacklinks&iwbllimit=max&iwblprop=iwtitle&iwblprefix=wikipedia
 * This example uses 'wikipedia', but for other interwiki prefixes it is enough
 * to change the value of 'iwblprefix' accordingly.
 *
 * Note that API queries are always limited, so if given interwiki prefix has
 * more than 500 (or 5000 if you have the 'apihighlimits' right) backlinks, it
 * will be necessary to continue the search as described in
 *     https://www.mediawiki.org/wiki/API:Query#Continuing_queries
 *
 * Also note that such queries do not find (all) interwiki redirects, if
 * present. A search like this should do the job instead:
 *     https://wiki.archlinux.org/index.php?title=Special%3ASearch&profile=advanced&limit=500&offset=0&search=%22redirect%20%5B%5Bde%3A%22&fulltext=Search&ns0=1&ns1=1&ns2=1&ns3=1&ns4=1&ns5=1&ns6=1&ns7=1&ns8=1&ns9=1&ns10=1&ns11=1&ns12=1&ns13=1&ns14=1&ns15=1&redirs=1&profile=advanced
 */

-- Clear the table, we don't want the entries from maintenance/interwiki.sql
DELETE FROM interwiki;

-- Arch's interlanguage prefixes
INSERT INTO
	interwiki (iw_prefix, iw_url, iw_local, iw_trans)
VALUES
	('ar', 'https://wiki.archlinux.org/index.php/$1_(%D8%A7%D9%84%D8%B9%D8%B1%D8%A8%D9%8A%D8%A9)', 1, 0),
	('bg', 'https://wiki.archlinux.org/index.php/$1_(%D0%91%D1%8A%D0%BB%D0%B3%D0%B0%D1%80%D1%81%D0%BA%D0%B8)', 1, 0),
	('cs', 'https://wiki.archlinux.org/index.php/$1_(%C4%8Cesky)', 1, 0),
	('da', 'https://wiki.archlinux.org/index.php/$1_(Dansk)', 1, 0),
	('de', 'https://wiki.archlinux.de/title/$1', 1, 0),
	('el', 'https://wiki.archlinux.org/index.php/$1_(%CE%95%CE%BB%CE%BB%CE%B7%CE%BD%CE%B9%CE%BA%CE%AC)', 1, 0),
	('en', 'https://wiki.archlinux.org/index.php/$1', 1, 0),
	('es', 'https://wiki.archlinux.org/index.php/$1_(Espa%C3%B1ol)', 1, 0),
	('fa', 'http://wiki.archusers.ir/index.php/$1', 1, 0),
	('fi', 'http://www.archlinux.fi/wiki/$1', 1, 0),
	('fr', 'http://wiki.archlinux.fr/$1', 1, 0),
	('he', 'https://wiki.archlinux.org/index.php/$1_(%D7%A2%D7%91%D7%A8%D7%99%D7%AA)', 1, 0),
	('hr', 'https://wiki.archlinux.org/index.php/$1_(Hrvatski)', 1, 0),
	('hu', 'https://wiki.archlinux.org/index.php/$1_(Magyar)', 1, 0),
	('id', 'https://wiki.archlinux.org/index.php/$1_(Indonesia)', 1, 0),
	('it', 'https://wiki.archlinux.org/index.php/$1_(Italiano)', 1, 0),
	('ja', 'https://wiki.archlinuxjp.org/index.php/$1', 1, 0),
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

-- Other interwiki prefixes
INSERT INTO interwiki (iw_prefix,iw_url,iw_local,iw_api) VALUES
('arxiv','http://www.arxiv.org/abs/$1',0,''),
('debian','https://wiki.debian.org/$1',0,''),
('doi','http://dx.doi.org/$1',0,''),
('emacswiki','http://www.emacswiki.org/cgi-bin/wiki.pl?$1',0,''),
('foldoc','http://foldoc.org/?$1',0,''),
('freebsdman','http://www.freebsd.org/cgi/man.cgi?query=$1',0,''),
('funtoo','http://www.funtoo.org/$1',0,'http://www.funtoo.org/api.php'),
('gentoo','https://wiki.gentoo.org/wiki/$1',0,'https://wiki.gentoo.org/api.php'),
('gregswiki','http://mywiki.wooledge.org/$1',0,''),
('linuxwiki','http://linuxwiki.de/$1',0,''),
('lqwiki','http://wiki.linuxquestions.org/wiki/$1',0,''),
('mozillawiki','http://wiki.mozilla.org/$1',0,'https://wiki.mozilla.org/api.php'),
('rfc','http://www.rfc-editor.org/rfc/rfc$1.txt',0,''),
('sourceforge','http://sourceforge.net/$1',0,''),
('wikia','http://www.wikia.com/wiki/$1',0,'')
;

-- Wikimedia Foundation projects and related
-- based on this table: https://meta.wikimedia.org/wiki/Help:Interwiki_linking#Project_titles_and_shortcuts
INSERT INTO interwiki (iw_prefix,iw_url,iw_local,iw_api) VALUES
('wikipedia','https://en.wikipedia.org/wiki/$1',0,'https://en.wikipedia.org/w/api.php'),
('w','https://en.wikipedia.org/wiki/$1',0,'https://en.wikipedia.org/w/api.php'),
('wiktionary','https://en.wiktionary.org/wiki/$1',0,'https://en.wiktionary.org/w/api.php'),
('wikt','https://en.wiktionary.org/wiki/$1',0,'https://en.wiktionary.org/w/api.php'),
('wikinews','https://en.wikinews.org/wiki/$1',0,'https://en.wikinews.org/w/api.php'),
('wikibooks','https://en.wikibooks.org/wiki/$1',0,'https://en.wikibooks.org/w/api.php'),
('wikiquote','https://en.wikiquote.org/wiki/$1',0,'https://en.wikiquote.org/w/api.php'),
('wikisource','https://wikisource.org/wiki/$1',0,'https://wikisource.org/w/api.php'),
('wikispecies','https://species.wikimedia.org/wiki/$1',0,'https://species.wikimedia.org/w/api.php'),
('wikiversity','https://en.wikiversity.org/wiki/$1',0,'https://en.wikiversity.org/w/api.php'),
('wikivoyage','https://en.wikivoyage.org/wiki/$1',0,'https://en.wikivoyage.org/w/api.php'),
('wikimedia','https://wikimediafoundation.org/wiki/$1',0,'https://wikimediafoundation.org/w/api.php'),
('wmf','https://wikimediafoundation.org/wiki/$1',0,'https://wikimediafoundation.org/w/api.php'),
('commons','https://commons.wikimedia.org/wiki/$1',0,'https://commons.wikimedia.org/w/api.php'),
('metawikimedia','https://meta.wikimedia.org/wiki/$1',0,'https://meta.wikimedia.org/w/api.php'),
('meta','https://meta.wikimedia.org/wiki/$1',0,'https://meta.wikimedia.org/w/api.php'),
('mw','https://www.mediawiki.org/wiki/$1',0,'https://www.mediawiki.org/w/api.php'),
('phabricator','https://phabricator.wikimedia.org/$1',0,''),
('phab','https://phabricator.wikimedia.org/$1',0,'')
;
