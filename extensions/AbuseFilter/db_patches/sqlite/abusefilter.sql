-- SQL tables for AbuseFilter extension

CREATE TABLE /*$wgDBprefix*/abuse_filter (
	af_id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	af_pattern BLOB NOT NULL,
	af_user BIGINT unsigned NOT NULL,
	af_user_text varbinary(255) NOT NULL,
	af_timestamp varbinary(14) NOT NULL,
	af_enabled tinyint(1) not null default 1,
	af_comments BLOB,
	af_public_comments TINYBLOB,
	af_hidden tinyint(1) not null default 0,
	af_hit_count bigint not null default 0,
	af_throttled tinyint(1) NOT NULL default 0,
	af_deleted tinyint(1) NOT NULL DEFAULT 0,
	af_actions varbinary(255) NOT NULL DEFAULT '',
	af_global tinyint(1) NOT NULL DEFAULT 0,
	af_group varbinary(64) NOT NULL DEFAULT 'default'
) /*$wgDBTableOptions*/;
CREATE INDEX af_user ON /*$wgDBprefix*/abuse_filter (af_user);
CREATE INDEX af_group_enabled ON /*$wgDBprefix*/abuse_filter (af_group,af_enabled,af_id);

CREATE TABLE /*$wgDBprefix*/abuse_filter_action (
	afa_filter INTEGER NOT NULL,
	afa_consequence varbinary(255) NOT NULL,
	afa_parameters TINYBLOB NOT NULL,

	PRIMARY KEY (afa_filter,afa_consequence)
) /*$wgDBTableOptions*/;
CREATE INDEX afa_consequence ON /*$wgDBprefix*/abuse_filter_action (afa_consequence);

CREATE TABLE /*$wgDBprefix*/abuse_filter_log (
	afl_id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	afl_global tinyint(1) NOT NULL,
	afl_filter_id INTEGER NOT NULL,
	afl_user BIGINT unsigned NOT NULL,
	afl_user_text varbinary(255) NOT NULL,
	afl_ip varbinary(255) not null,
	afl_action varbinary(255) not null,
	afl_actions varbinary(255) not null,
	afl_var_dump BLOB NOT NULL,
	afl_timestamp varbinary(14) NOT NULL,
	afl_namespace int NOT NULL,
	afl_title varbinary(255) NOT NULL,
	afl_wiki varbinary(64) NULL,
	afl_deleted tinyint(1) NOT NULL DEFAULT 0,
	afl_patrolled_by int unsigned NOT NULL DEFAULT 0,
	afl_rev_id int unsigned
) /*$wgDBTableOptions*/;
CREATE INDEX afl_filter_timestamp_full ON /*$wgDBprefix*/abuse_filter_log (afl_global,afl_filter_id,afl_timestamp);
CREATE INDEX afl_user_timestamp ON /*$wgDBprefix*/abuse_filter_log (afl_user,afl_user_text,afl_timestamp);
CREATE INDEX afl_timestamp ON /*$wgDBprefix*/abuse_filter_log  (afl_timestamp);
CREATE INDEX afl_page_timestamp ON /*$wgDBprefix*/abuse_filter_log (afl_namespace, afl_title, afl_timestamp);
CREATE INDEX afl_ip_timestamp ON /*$wgDBprefix*/abuse_filter_log (afl_ip, afl_timestamp);
CREATE INDEX afl_wiki_timestamp ON /*$wgDBprefix*/abuse_filter_log (afl_wiki, afl_timestamp);
CREATE INDEX afl_rev_id ON /*$wgDBprefix*/abuse_filter_log (afl_rev_id);

CREATE TABLE /*$wgDBprefix*/abuse_filter_history (
	afh_id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	afh_filter INTEGER NOT NULL,
	afh_user BIGINT unsigned NOT NULL,
	afh_user_text varbinary(255) NOT NULL,
	afh_timestamp varbinary(14) NOT NULL,
	afh_pattern BLOB NOT NULL,
	afh_comments BLOB NOT NULL,
	afh_flags TINYBLOB NOT NULL,
	afh_public_comments TINYBLOB,
	afh_actions BLOB,
	afh_deleted tinyint(1) NOT NULL DEFAULT 0,
	afh_changed_fields varbinary(255) NOT NULL DEFAULT '',
	afh_group varbinary(64) NULL
) /*$wgDBTableOptions*/;
CREATE INDEX afh_filter ON /*$wgDBprefix*/abuse_filter_history (afh_filter);
CREATE INDEX afh_user ON /*$wgDBprefix*/abuse_filter_history (afh_user);
CREATE INDEX afh_user_text ON /*$wgDBprefix*/abuse_filter_history (afh_user_text);
CREATE INDEX afh_timestamp ON /*$wgDBprefix*/abuse_filter_history (afh_timestamp);
