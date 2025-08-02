-- Make cuc_id in cu_changes unsigned
ALTER TABLE /*_*/cu_changes
    CHANGE cuc_id cuc_id INTEGER UNSIGNED NOT NULL AUTO_INCREMENT;
