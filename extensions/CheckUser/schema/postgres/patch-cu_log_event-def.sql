CREATE TABLE cu_log_event (
  cule_id BIGSERIAL NOT NULL,
  cule_log_id INT DEFAULT 0 NOT NULL,
  cule_actor BIGINT NOT NULL,
  cule_timestamp TIMESTAMPTZ NOT NULL,
  cule_ip VARCHAR(255) DEFAULT '',
  cule_ip_hex VARCHAR(255) DEFAULT NULL,
  cule_xff TEXT DEFAULT '',
  cule_xff_hex VARCHAR(255) DEFAULT NULL,
  cule_agent TEXT DEFAULT NULL,
  PRIMARY KEY(cule_id)
);

CREATE INDEX cule_ip_hex_time ON cu_log_event (cule_ip_hex, cule_timestamp);

CREATE INDEX cule_xff_hex_time ON cu_log_event (cule_xff_hex, cule_timestamp);

CREATE INDEX cule_timestamp ON cu_log_event (cule_timestamp);

CREATE INDEX cule_actor_ip_time ON cu_log_event (
  cule_actor, cule_ip, cule_timestamp
);
