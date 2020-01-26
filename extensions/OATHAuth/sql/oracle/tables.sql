define mw_prefix='{$wgDBprefix}';

CREATE SEQUENCE oathauth_users_id_seq;
CREATE TABLE &mw_prefix.oathauth_users (
	-- User ID
	id NUMBER NOT NULL,

	-- Module selected by user
	module VARCHAR2(255) NULL,

	-- Module data
	data BLOB NULL

);
ALTER TABLE &mw_prefix.oathauth_users ADD CONSTRAINT &mw_prefix.oathauth_users_pk PRIMARY KEY (id);
