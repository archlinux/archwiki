CREATE TABLE cu_private_event (
  cupe_id BIGSERIAL NOT NULL,
  cupe_namespace INT DEFAULT 0 NOT NULL,
  cupe_title TEXT DEFAULT '' NOT NULL,
  cupe_actor BIGINT DEFAULT 0 NOT NULL,
  cupe_log_type TEXT DEFAULT '' NOT NULL,
  cupe_log_action TEXT DEFAULT '' NOT NULL,
  cupe_params TEXT NOT NULL,
  cupe_comment_id BIGINT DEFAULT 0 NOT NULL,
  cupe_page INT DEFAULT 0 NOT NULL,
  cupe_timestamp TIMESTAMPTZ NOT NULL,
  cupe_ip VARCHAR(255) DEFAULT '',
  cupe_ip_hex VARCHAR(255) DEFAULT NULL,
  cupe_xff TEXT DEFAULT '',
  cupe_xff_hex VARCHAR(255) DEFAULT NULL,
  cupe_agent TEXT DEFAULT NULL,
  cupe_private TEXT DEFAULT NULL,
  PRIMARY KEY(cupe_id)
);

CREATE INDEX cupe_ip_hex_time ON cu_private_event (cupe_ip_hex, cupe_timestamp);

CREATE INDEX cupe_xff_hex_time ON cu_private_event (cupe_xff_hex, cupe_timestamp);

CREATE INDEX cupe_timestamp ON cu_private_event (cupe_timestamp);

CREATE INDEX cupe_actor_ip_time ON cu_private_event (
  cupe_actor, cupe_ip, cupe_timestamp
);
