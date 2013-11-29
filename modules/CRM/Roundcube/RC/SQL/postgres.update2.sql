ALTER TABLE rc_cache DROP COLUMN cache_id;
DROP SEQUENCE rc_cache_ids;

ALTER TABLE rc_users DROP COLUMN alias;
CREATE INDEX rc_identities_email_idx ON rc_identities (email, del);

CREATE TABLE rc_system (
    name varchar(64) NOT NULL PRIMARY KEY,
    value text
);
INSERT INTO rc_system (name, value) VALUES ('roundcube-version', '2013011700');