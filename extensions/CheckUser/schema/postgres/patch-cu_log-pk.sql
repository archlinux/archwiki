DROP  INDEX cul_user;
DROP  INDEX cul_type_target;
DROP  INDEX cul_target_hex;
DROP  INDEX cul_range_start;
CREATE INDEX cul_user ON cu_log (cul_user, cul_timestamp);
CREATE INDEX cul_type_target ON cu_log (cul_type, cul_target_id, cul_timestamp);
CREATE INDEX cul_target_hex ON cu_log (cul_target_hex, cul_timestamp);
CREATE INDEX cul_range_start ON cu_log (cul_range_start, cul_timestamp);
ALTER TABLE  cu_log ADD  PRIMARY KEY (cul_id);
