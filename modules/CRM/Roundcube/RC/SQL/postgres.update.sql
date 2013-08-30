ALTER TABLE rc_contacts ADD words TEXT NULL;
CREATE INDEX rc_contactgroupmembers_contact_id_idx ON rc_contactgroupmembers (contact_id);
TRUNCATE rc_messages;
TRUNCATE rc_cache;
CREATE TABLE rc_dictionary (
    user_id integer DEFAULT NULL
        REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
   "language" varchar(5) NOT NULL,
    data text NOT NULL,
    CONSTRAINT dictionary_user_id_language_key UNIQUE (user_id, "language")
);
CREATE SEQUENCE rc_search_ids
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
CREATE TABLE rc_searches (
    search_id integer DEFAULT nextval('rc_search_ids'::text) PRIMARY KEY,
    user_id integer NOT NULL
        REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    "type" smallint DEFAULT 0 NOT NULL,
    name varchar(128) NOT NULL,
    data text NOT NULL,
    CONSTRAINT searches_user_id_key UNIQUE (user_id, "type", name)
);
DROP SEQUENCE rc_message_ids;
DROP TABLE rc_messages;
CREATE TABLE rc_cache_index (
    user_id integer NOT NULL
    	REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    mailbox varchar(255) NOT NULL,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    valid smallint NOT NULL DEFAULT 0,
    data text NOT NULL,
    PRIMARY KEY (user_id, mailbox)
);
CREATE INDEX rc_cache_index_changed_idx ON rc_cache_index (changed);
CREATE TABLE rc_cache_thread (
    user_id integer NOT NULL
    	REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    mailbox varchar(255) NOT NULL,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    data text NOT NULL,
    PRIMARY KEY (user_id, mailbox)
);
CREATE INDEX rc_cache_thread_changed_idx ON rc_cache_thread (changed);
CREATE TABLE rc_cache_messages (
    user_id integer NOT NULL
    	REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    mailbox varchar(255) NOT NULL,
    uid integer NOT NULL,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    data text NOT NULL,
    flags integer NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, mailbox, uid)
);
CREATE INDEX rc_cache_messages_changed_idx ON rc_cache_messages (changed);
ALTER TABLE "rc_session" ALTER sess_id TYPE varchar(128);
DROP INDEX rc_contacts_user_id_idx;
CREATE INDEX rc_contacts_user_id_idx ON rc_contacts USING btree (user_id, del);
ALTER TABLE rc_contacts ALTER email TYPE text;
