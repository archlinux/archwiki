CREATE TABLE /*_*/cu_log_event (
  cule_id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  cule_log_id INTEGER UNSIGNED DEFAULT 0 NOT NULL,
  cule_actor BIGINT UNSIGNED NOT NULL,
  cule_timestamp BLOB NOT NULL,
  cule_ip VARCHAR(255) DEFAULT '',
  cule_ip_hex VARCHAR(255) DEFAULT NULL,
  cule_xff BLOB DEFAULT '',
  cule_xff_hex VARCHAR(255) DEFAULT NULL,
  cule_agent BLOB DEFAULT NULL
);

CREATE INDEX cule_ip_hex_time ON /*_*/cu_log_event (cule_ip_hex, cule_timestamp);

CREATE INDEX cule_xff_hex_time ON /*_*/cu_log_event (cule_xff_hex, cule_timestamp);

CREATE INDEX cule_timestamp ON /*_*/cu_log_event (cule_timestamp);

CREATE INDEX cule_actor_ip_time ON /*_*/cu_log_event (
  cule_actor, cule_ip, cule_timestamp
);
