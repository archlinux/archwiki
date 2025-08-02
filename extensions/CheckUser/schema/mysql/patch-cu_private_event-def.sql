CREATE TABLE /*_*/cu_private_event (
  cupe_id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  cupe_namespace INT DEFAULT 0 NOT NULL,
  cupe_title VARBINARY(255) DEFAULT '' NOT NULL,
  cupe_actor BIGINT UNSIGNED DEFAULT 0 NOT NULL,
  cupe_log_type VARBINARY(32) DEFAULT '' NOT NULL,
  cupe_log_action VARBINARY(32) DEFAULT '' NOT NULL,
  cupe_params BLOB NOT NULL,
  cupe_comment_id BIGINT UNSIGNED DEFAULT 0 NOT NULL,
  cupe_page INT UNSIGNED DEFAULT 0 NOT NULL,
  cupe_timestamp BINARY(14) NOT NULL,
  cupe_ip VARCHAR(255) DEFAULT '',
  cupe_ip_hex VARCHAR(255) DEFAULT NULL,
  cupe_xff VARBINARY(255) DEFAULT '',
  cupe_xff_hex VARCHAR(255) DEFAULT NULL,
  cupe_agent VARBINARY(255) DEFAULT NULL,
  cupe_private MEDIUMBLOB DEFAULT NULL,
  INDEX cupe_ip_hex_time (cupe_ip_hex, cupe_timestamp),
  INDEX cupe_xff_hex_time (cupe_xff_hex, cupe_timestamp),
  INDEX cupe_timestamp (cupe_timestamp),
  INDEX cupe_actor_ip_time (
    cupe_actor, cupe_ip, cupe_timestamp
  ),
  PRIMARY KEY(cupe_id)
) /*$wgDBTableOptions*/;
