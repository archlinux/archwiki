-- Standardise type for timestamp columns
ALTER TABLE  /*_*/cu_changes
CHANGE  cuc_timestamp cuc_timestamp BINARY(14) NOT NULL;
