CREATE TABLE /*_*/page_tmp (
  page_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  page_namespace INTEGER NOT NULL,
  page_title BLOB NOT NULL,
  page_restrictions BLOB DEFAULT NULL,
  page_is_redirect SMALLINT DEFAULT 0 NOT NULL,
  page_is_new SMALLINT DEFAULT 0 NOT NULL,
  page_random DOUBLE PRECISION NOT NULL,
  page_touched BLOB NOT NULL,
  page_links_updated BLOB DEFAULT NULL,
  page_latest INTEGER UNSIGNED NOT NULL,
  page_len INTEGER UNSIGNED NOT NULL,
  page_content_model BLOB DEFAULT NULL,
  page_lang BLOB DEFAULT NULL
);


INSERT INTO /*_*/page_tmp
       SELECT page_id, page_namespace, page_title, page_restrictions, page_is_redirect, page_is_new,
            page_random, page_touched, page_links_updated, page_latest, page_len, page_content_model, page_lang
               FROM /*_*/page;
DROP TABLE /*_*/page;
ALTER TABLE /*_*/page_tmp RENAME TO /*_*/page;

CREATE UNIQUE INDEX name_title ON /*_*/page (page_namespace, page_title);

CREATE INDEX page_random ON /*_*/page (page_random);

CREATE INDEX page_len ON /*_*/page (page_len);

CREATE INDEX page_redirect_namespace_len ON /*_*/page (
  page_is_redirect, page_namespace,
  page_len
);
