CREATE TABLE recentchanges_tmp (
  rc_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  rc_timestamp BLOB NOT NULL, rc_actor BIGINT UNSIGNED NOT NULL,
  rc_namespace INTEGER DEFAULT 0 NOT NULL,
  rc_title BLOB DEFAULT '' NOT NULL, rc_comment_id BIGINT UNSIGNED NOT NULL,
  rc_minor SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
  rc_bot SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
  rc_new SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
  rc_cur_id INTEGER UNSIGNED DEFAULT 0 NOT NULL,
  rc_this_oldid INTEGER UNSIGNED DEFAULT 0 NOT NULL,
  rc_last_oldid INTEGER UNSIGNED DEFAULT 0 NOT NULL,
  rc_type SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
  rc_source BLOB DEFAULT '' NOT NULL,
  rc_patrolled SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
  rc_ip BLOB DEFAULT '' NOT NULL, rc_old_len INTEGER DEFAULT NULL,
  rc_new_len INTEGER DEFAULT NULL, rc_deleted SMALLINT UNSIGNED DEFAULT 0 NOT NULL,
  rc_logid INTEGER UNSIGNED DEFAULT 0 NOT NULL,
  rc_log_type BLOB DEFAULT NULL, rc_log_action BLOB DEFAULT NULL,
  rc_params BLOB DEFAULT NULL
);
INSERT INTO /*_*/recentchanges_tmp (
  rc_id, rc_timestamp, rc_actor, rc_namespace, rc_title, rc_comment_id, rc_minor, rc_bot, rc_new, rc_cur_id,
  rc_this_oldid, rc_last_oldid, rc_type, rc_source, rc_patrolled, rc_ip, rc_old_len, rc_new_len, rc_deleted,
  rc_logid, rc_log_type, rc_log_action, rc_params)
SELECT rc_id, rc_timestamp, rc_actor, rc_namespace, rc_title, rc_comment_id, rc_minor, rc_bot, rc_new, rc_cur_id,
  rc_this_oldid, rc_last_oldid, rc_type, rc_source, rc_patrolled, rc_ip, rc_old_len, rc_new_len, rc_deleted,
  rc_logid, rc_log_type, rc_log_action, rc_params
FROM /*_*/recentchanges;
DROP TABLE /*_*/recentchanges;
ALTER TABLE /*_*/recentchanges_tmp RENAME TO /*_*/recentchanges;


CREATE INDEX rc_timestamp ON /*_*/recentchanges (rc_timestamp);
CREATE INDEX rc_namespace_title_timestamp ON /*_*/recentchanges (
  rc_namespace, rc_title, rc_timestamp
);
CREATE INDEX rc_cur_id ON /*_*/recentchanges (rc_cur_id);
CREATE INDEX new_name_timestamp ON /*_*/recentchanges (
  rc_new, rc_namespace, rc_timestamp
);
CREATE INDEX rc_ip ON /*_*/recentchanges (rc_ip);
CREATE INDEX rc_ns_actor ON /*_*/recentchanges (rc_namespace, rc_actor);
CREATE INDEX rc_actor ON /*_*/recentchanges (rc_actor, rc_timestamp);
CREATE INDEX rc_name_type_patrolled_timestamp ON /*_*/recentchanges (
  rc_namespace, rc_type, rc_patrolled,
  rc_timestamp
);
CREATE INDEX rc_this_oldid ON /*_*/recentchanges (rc_this_oldid);
