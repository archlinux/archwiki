CREATE TABLE oldimage_tmp (
  oi_name BLOB DEFAULT '' NOT NULL, oi_archive_name BLOB DEFAULT '' NOT NULL,
  oi_size INTEGER UNSIGNED DEFAULT 0 NOT NULL,
  oi_width INTEGER DEFAULT 0 NOT NULL,
  oi_height INTEGER DEFAULT 0 NOT NULL,
  oi_bits INTEGER DEFAULT 0 NOT NULL,
  oi_description_id BIGINT UNSIGNED NOT NULL,
  oi_actor BIGINT UNSIGNED NOT NULL,
  oi_timestamp BLOB NOT NULL,
  oi_metadata BLOB NOT NULL, oi_media_type TEXT DEFAULT NULL,
  oi_major_mime TEXT DEFAULT 'unknown' NOT NULL,
  oi_minor_mime BLOB DEFAULT 'unknown' NOT NULL,
  oi_deleted SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
  oi_sha1 BLOB DEFAULT '' NOT NULL
);;
INSERT INTO /*_*/oldimage_tmp (
  oi_name, oi_archive_name,oi_size,oi_width,oi_height ,oi_bits ,oi_description_id ,oi_actor, oi_timestamp,oi_metadata, oi_media_type,oi_major_mime,
  oi_minor_mime, oi_deleted, oi_sha1)
SELECT oi_name, oi_archive_name,oi_size,oi_width,oi_height ,oi_bits ,oi_description_id ,oi_actor, oi_timestamp,oi_metadata, oi_media_type,oi_major_mime,
       oi_minor_mime, oi_deleted, oi_sha1
FROM /*_*/oldimage;
DROP TABLE /*_*/oldimage;
ALTER TABLE /*_*/oldimage_tmp RENAME TO /*_*/oldimage;

CREATE INDEX oi_actor_timestamp ON /*_*/oldimage (oi_actor, oi_timestamp);
CREATE INDEX oi_name_timestamp ON /*_*/oldimage (oi_name, oi_timestamp);
CREATE INDEX oi_name_archive_name ON /*_*/oldimage (oi_name, oi_archive_name);
CREATE INDEX oi_sha1 ON /*_*/oldimage (oi_sha1);
