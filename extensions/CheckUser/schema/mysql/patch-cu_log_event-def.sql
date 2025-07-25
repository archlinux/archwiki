CREATE TABLE /*_*/cu_log_event (
  cule_id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
  cule_log_id INT UNSIGNED DEFAULT 0 NOT NULL,
  cule_actor BIGINT UNSIGNED NOT NULL,
  cule_timestamp BINARY(14) NOT NULL,
  cule_ip VARCHAR(255) DEFAULT '',
  cule_ip_hex VARCHAR(255) DEFAULT NULL,
  cule_xff VARBINARY(255) DEFAULT '',
  cule_xff_hex VARCHAR(255) DEFAULT NULL,
  cule_agent VARBINARY(255) DEFAULT NULL,
  INDEX cule_ip_hex_time (cule_ip_hex, cule_timestamp),
  INDEX cule_xff_hex_time (cule_xff_hex, cule_timestamp),
  INDEX cule_timestamp (cule_timestamp),
  INDEX cule_actor_ip_time (
    cule_actor, cule_ip, cule_timestamp
  ),
  PRIMARY KEY(cule_id)
) /*$wgDBTableOptions*/;
