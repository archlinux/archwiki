DROP INDEX change_tag_rc_tag_nonuniq ON /*_*/change_tag;
DROP INDEX change_tag_log_tag_nonuniq ON /*_*/change_tag;
DROP INDEX change_tag_rev_tag_nonuniq ON /*_*/change_tag;
DROP INDEX change_tag_tag_id ON /*_*/change_tag;
ALTER TABLE /*_*/change_tag DROP ct_tag, CHANGE ct_tag_id ct_tag_id INT UNSIGNED NOT NULL;
