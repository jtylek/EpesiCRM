ALTER SEQUENCE rc_user_ids RENAME TO rc_users_seq;
ALTER TABLE rc_users ALTER COLUMN user_id SET DEFAULT nextval('rc_users_seq'::text);

ALTER SEQUENCE rc_identity_ids RENAME TO rc_identities_seq;
ALTER TABLE rc_identities ALTER COLUMN identity_id SET DEFAULT nextval('rc_identities_seq'::text);

ALTER SEQUENCE rc_contact_ids RENAME TO rc_contacts_seq;
ALTER TABLE rc_contacts ALTER COLUMN contact_id SET DEFAULT nextval('rc_contacts_seq'::text);

ALTER SEQUENCE rc_contactgroups_ids RENAME TO rc_contactgroups_seq;
ALTER TABLE rc_contactgroups ALTER COLUMN contactgroup_id SET DEFAULT nextval('rc_contactgroups_seq'::text);

ALTER SEQUENCE rc_search_ids RENAME TO rc_searches_seq;
ALTER TABLE rc_searches ALTER COLUMN search_id SET DEFAULT nextval('rc_searches_seq'::text);

CREATE TABLE "rc_cache_shared" (
    cache_key varchar(255) NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    data text NOT NULL
);

CREATE INDEX rc_cache_shared_cache_key_idx ON "rc_cache_shared" (cache_key);
CREATE INDEX rc_cache_shared_created_idx ON "rc_cache_shared" (created);

ALTER TABLE "rc_cache" ADD expires timestamp with time zone DEFAULT NULL;
ALTER TABLE "rc_cache_shared" ADD expires timestamp with time zone DEFAULT NULL;
ALTER TABLE "rc_cache_index" ADD expires timestamp with time zone DEFAULT NULL;
ALTER TABLE "rc_cache_thread" ADD expires timestamp with time zone DEFAULT NULL;
ALTER TABLE "rc_cache_messages" ADD expires timestamp with time zone DEFAULT NULL;

-- initialize expires column with created/changed date + 7days
UPDATE "rc_cache" SET expires = created + interval '604800 seconds';
UPDATE "rc_cache_shared" SET expires = created + interval '604800 seconds';
UPDATE "rc_cache_index" SET expires = changed + interval '604800 seconds';
UPDATE "rc_cache_thread" SET expires = changed + interval '604800 seconds';
UPDATE "rc_cache_messages" SET expires = changed + interval '604800 seconds';

DROP INDEX rc_cache_created_idx;
DROP INDEX rc_cache_shared_created_idx;
ALTER TABLE "rc_cache_index" DROP "changed";
ALTER TABLE "rc_cache_thread" DROP "changed";
ALTER TABLE "rc_cache_messages" DROP "changed";

CREATE INDEX rc_cache_expires_idx ON "rc_cache" (expires);
CREATE INDEX rc_cache_shared_expires_idx ON "rc_cache_shared" (expires);
CREATE INDEX rc_cache_index_expires_idx ON "rc_cache_index" (expires);
CREATE INDEX rc_cache_thread_expires_idx ON "rc_cache_thread" (expires);
CREATE INDEX rc_cache_messages_expires_idx ON "rc_cache_messages" (expires);
