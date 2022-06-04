-- Split afl_filter into afl_filter_id and afl_global
ALTER TABLE /*_*/abuse_filter_log
	ADD COLUMN afl_global tinyint(1) NOT NULL DEFAULT 0 AFTER afl_filter,
	ADD COLUMN afl_filter_id BIGINT unsigned NOT NULL DEFAULT 0 AFTER afl_global,
	ALTER COLUMN afl_filter SET DEFAULT '';

CREATE INDEX /*i*/afl_filter_timestamp_full ON /*_*/abuse_filter_log (afl_global,afl_filter_id,afl_timestamp);
