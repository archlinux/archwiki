-- SQL tables for AbuseFilter extension (Postgres version)

BEGIN;

CREATE SEQUENCE abuse_filter_af_id_seq;
CREATE TABLE abuse_filter (
    af_id               INTEGER      NOT NULL PRIMARY KEY DEFAULT nextval('abuse_filter_af_id_seq'),
    af_pattern          TEXT         NOT NULL,
    af_user             INTEGER      NOT NULL,
    af_user_text        TEXT         NOT NULL,
    af_timestamp        TIMESTAMPTZ  NOT NULL,
    af_enabled          SMALLINT     NOT NULL             DEFAULT 1,
    af_comments         TEXT,
    af_public_comments  TEXT,
    af_hidden           SMALLINT     NOT NULL             DEFAULT 0,
    af_hit_count        INTEGER      NOT NULL             DEFAULT 0,
    af_throttled        SMALLINT     NOT NULL             DEFAULT 0,
    af_deleted          SMALLINT     NOT NULL             DEFAULT 0,
    af_actions          TEXT         NOT NULL             DEFAULT '',
    af_global           SMALLINT     NOT NULL             DEFAULT 0,
    af_group            TEXT         NOT NULL             DEFAULT 'default'
);
CREATE INDEX abuse_filter_user ON abuse_filter(af_user);
CREATE INDEX abuse_filter_group_enabled_id ON abuse_filter(af_group,af_enabled,af_id);


CREATE TABLE abuse_filter_action (
    afa_filter       INTEGER  NOT NULL,
    afa_consequence  TEXT     NOT NULL,
    afa_parameters   TEXT     NOT NULL,
    PRIMARY KEY (afa_filter,afa_consequence)
);
CREATE INDEX abuse_filter_action_consequence ON abuse_filter_action(afa_consequence);


CREATE SEQUENCE abuse_filter_log_afl_id_seq;
CREATE TABLE abuse_filter_log (
    afl_id           INTEGER     NOT NULL PRIMARY KEY DEFAULT nextval('abuse_filter_log_afl_id_seq'),
    afl_user         INTEGER     NOT NULL,
    afl_user_text    TEXT        NOT NULL,
    afl_ip           TEXT        NOT NULL,
    afl_action       TEXT        NOT NULL,
    afl_actions      TEXT        NOT NULL,
    afl_var_dump     TEXT        NOT NULL,
    afl_timestamp    TIMESTAMPTZ NOT NULL,
    afl_namespace    INTEGER     NOT NULL,
    afl_title        TEXT        NOT NULL,
    afl_wiki         TEXT            NULL,
    afl_deleted      SMALLINT    NOT NULL             DEFAULT 0,
    afl_patrolled_by INTEGER     NOT NULL             DEFAULT 0,
    afl_rev_id       INTEGER         NULL,
    afl_global       SMALLINT    NOT NULL,
    afl_filter_id    INTEGER     NOT NULL
);

CREATE INDEX abuse_filter_log_filter_timestamp_full ON abuse_filter_log(afl_global,afl_filter_id,afl_timestamp);
CREATE INDEX abuse_filter_log_user_timestamp        ON abuse_filter_log(afl_user,afl_user_text,afl_timestamp);
CREATE INDEX abuse_filter_log_timestamp             ON abuse_filter_log(afl_timestamp);
CREATE INDEX abuse_filter_log_page_timestamp        ON abuse_filter_log(afl_namespace, afl_title, afl_timestamp);
CREATE INDEX abuse_filter_log_ip_timestamp          ON abuse_filter_log(afl_ip, afl_timestamp);
CREATE INDEX abuse_filter_log_rev_id                ON abuse_filter_log(afl_rev_id);
CREATE INDEX abuse_filter_log_wiki_timestamp        ON abuse_filter_log(afl_wiki, afl_timestamp);

CREATE SEQUENCE abuse_filter_history_afh_id_seq;
CREATE TABLE abuse_filter_history (
    afh_id               INTEGER      NOT NULL PRIMARY KEY DEFAULT nextval('abuse_filter_history_afh_id_seq'),
    afh_filter           INTEGER      NOT NULL,
    afh_user             INTEGER      NOT NULL,
    afh_user_text        TEXT         NOT NULL,
    afh_timestamp        TIMESTAMPTZ  NOT NULL,
    afh_pattern          TEXT         NOT NULL,
    afh_comments         TEXT         NOT NULL,
    afh_flags            TEXT         NOT NULL,
    afh_public_comments  TEXT         NOT NULL,
    afh_actions          TEXT         NOT NULL,
    afh_deleted          SMALLINT     NOT NULL             DEFAULT 0,
    afh_changed_fields   TEXT         NOT NULL             DEFAULT '',
    afh_group            TEXT         NULL
);
CREATE INDEX abuse_filter_history_filter    ON abuse_filter_history(afh_filter);
CREATE INDEX abuse_filter_history_user      ON abuse_filter_history(afh_user);
CREATE INDEX abuse_filter_history_user_text ON abuse_filter_history(afh_user_text);
CREATE INDEX abuse_filter_history_timestamp ON abuse_filter_history(afh_timestamp);

COMMIT;

