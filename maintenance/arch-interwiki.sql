-- This script is intended to update the default interwiki map created by
-- ./interwiki.sql to something more sane.

-- Clear the table, we don't want the defaults.
DELETE FROM /*$wgDBprefix*/interwiki;

INSERT INTO /*$wgDBprefix*/interwiki (iw_prefix,iw_url,iw_local,iw_api) VALUES
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
INSERT INTO /*$wgDBprefix*/interwiki (iw_prefix,iw_url,iw_local,iw_api) VALUES
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
