CREATE TABLE /*_*/cu_private_event (
  cupe_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  cupe_namespace INTEGER DEFAULT 0 NOT NULL,
  cupe_title BLOB DEFAULT '' NOT NULL,
  cupe_actor BIGINT UNSIGNED DEFAULT 0 NOT NULL,
  cupe_log_type BLOB DEFAULT '' NOT NULL,
  cupe_log_action BLOB DEFAULT '' NOT NULL,
  cupe_params BLOB NOT NULL,
  cupe_comment_id BIGINT UNSIGNED DEFAULT 0 NOT NULL,
  cupe_page INTEGER UNSIGNED DEFAULT 0 NOT NULL,
  cupe_timestamp BLOB NOT NULL,
  cupe_ip VARCHAR(255) DEFAULT '',
  cupe_ip_hex VARCHAR(255) DEFAULT NULL,
  cupe_xff BLOB DEFAULT '',
  cupe_xff_hex VARCHAR(255) DEFAULT NULL,
  cupe_agent BLOB DEFAULT NULL,
  cupe_private BLOB DEFAULT NULL
);

CREATE INDEX cupe_ip_hex_time ON /*_*/cu_private_event (cupe_ip_hex, cupe_timestamp);

CREATE INDEX cupe_xff_hex_time ON /*_*/cu_private_event (cupe_xff_hex, cupe_timestamp);

CREATE INDEX cupe_timestamp ON /*_*/cu_private_event (cupe_timestamp);

CREATE INDEX cupe_actor_ip_time ON /*_*/cu_private_event (
  cupe_actor, cupe_ip, cupe_timestamp
);
