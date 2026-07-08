DROP INDEX sic_url_identifier ON /*_*/cusi_case;

ALTER TABLE /*_*/cusi_case
  CHANGE sic_url_identifier sic_url_identifier INT UNSIGNED DEFAULT 0 NOT NULL;

CREATE INDEX sic_url_identifier ON /*_*/cusi_case (sic_url_identifier);
