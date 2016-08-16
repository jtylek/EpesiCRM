CREATE SEQUENCE rc_users_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
CREATE TABLE rc_users (
    user_id integer DEFAULT nextval('rc_users_seq'::text) PRIMARY KEY,
    username varchar(128) DEFAULT '' NOT NULL,
    mail_host varchar(128) DEFAULT '' NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    last_login timestamp with time zone DEFAULT NULL,
    failed_login timestamp with time zone DEFAULT NULL,
    failed_login_counter integer DEFAULT NULL,
    "language" varchar(5),
    preferences text DEFAULT ''::text NOT NULL,
    CONSTRAINT users_username_key UNIQUE (username, mail_host)
);
CREATE TABLE "rc_session" (
    sess_id varchar(128) DEFAULT '' PRIMARY KEY,
    created timestamp with time zone DEFAULT now() NOT NULL,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    ip varchar(41) NOT NULL,
    vars text NOT NULL
);
CREATE INDEX rc_session_changed_idx ON rc_session (changed);
CREATE SEQUENCE rc_identities_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
CREATE TABLE rc_identities (
    identity_id integer DEFAULT nextval('rc_identities_seq'::text) PRIMARY KEY,
    user_id integer NOT NULL
        REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    del smallint DEFAULT 0 NOT NULL,
    standard smallint DEFAULT 0 NOT NULL,
    name varchar(128) NOT NULL,
    organization varchar(128),
    email varchar(128) NOT NULL,
    "reply-to" varchar(128),
    bcc varchar(128),
    signature text,
    html_signature integer DEFAULT 0 NOT NULL
);
CREATE INDEX rc_identities_user_id_idx ON rc_identities (user_id, del);
CREATE INDEX rc_identities_email_idx ON rc_identities (email, del);
CREATE SEQUENCE rc_contacts_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
CREATE TABLE rc_contacts (
    contact_id integer DEFAULT nextval('rc_contacts_seq'::text) PRIMARY KEY,
    user_id integer NOT NULL
        REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    del smallint DEFAULT 0 NOT NULL,
    name varchar(128) DEFAULT '' NOT NULL,
    email text DEFAULT '' NOT NULL,
    firstname varchar(128) DEFAULT '' NOT NULL,
    surname varchar(128) DEFAULT '' NOT NULL,
    vcard text,
    words text
);
CREATE INDEX rc_contacts_user_id_idx ON rc_contacts (user_id, del);
CREATE SEQUENCE rc_contactgroups_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
CREATE TABLE rc_contactgroups (
    contactgroup_id integer DEFAULT nextval('rc_contactgroups_seq'::text) PRIMARY KEY,
    user_id integer NOT NULL
        REFERENCES rc_users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    del smallint NOT NULL DEFAULT 0,
    name varchar(128) NOT NULL DEFAULT ''
);
CREATE INDEX rc_contactgroups_user_id_idx ON rc_contactgroups (user_id, del);
CREATE TABLE rc_contactgroupmembers (
    contactgroup_id integer NOT NULL
        REFERENCES rc_contactgroups(contactgroup_id) ON DELETE CASCADE ON UPDATE CASCADE,
    contact_id integer NOT NULL
        REFERENCES rc_contacts(contact_id) ON DELETE CASCADE ON UPDATE CASCADE,
    created timestamp with time zone DEFAULT now() NOT NULL,
    PRIMARY KEY (contactgroup_id, contact_id)
);
CREATE INDEX rc_contactgroupmembers_contact_id_idx ON rc_contactgroupmembers (contact_id);
CREATE TABLE "rc_cache" (
    user_id integer NOT NULL
        REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    cache_key varchar(128) DEFAULT '' NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    expires timestamp with time zone DEFAULT NULL,
    data text NOT NULL
);
CREATE INDEX rc_cache_user_id_idx ON "rc_cache" (user_id, cache_key);
CREATE INDEX rc_cache_expires_idx ON "rc_cache" (expires);
CREATE TABLE "rc_cache_shared" (
    cache_key varchar(255) NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    expires timestamp with time zone DEFAULT NULL,
    data text NOT NULL
);
CREATE INDEX rc_cache_shared_cache_key_idx ON "rc_cache_shared" (cache_key);
CREATE INDEX rc_cache_shared_expires_idx ON "rc_cache_shared" (expires);
CREATE TABLE rc_cache_index (
    user_id integer NOT NULL
        REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    mailbox varchar(255) NOT NULL,
    expires timestamp with time zone DEFAULT NULL,
    valid smallint NOT NULL DEFAULT 0,
    data text NOT NULL,
    PRIMARY KEY (user_id, mailbox)
);
CREATE INDEX rc_cache_index_expires_idx ON rc_cache_index (expires);
CREATE TABLE rc_cache_thread (
    user_id integer NOT NULL
        REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    mailbox varchar(255) NOT NULL,
    expires timestamp with time zone DEFAULT NULL,
    data text NOT NULL,
    PRIMARY KEY (user_id, mailbox)
);
CREATE INDEX rc_cache_thread_expires_idx ON rc_cache_thread (expires);
CREATE TABLE rc_cache_messages (
    user_id integer NOT NULL
        REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    mailbox varchar(255) NOT NULL,
    uid integer NOT NULL,
    expires timestamp with time zone DEFAULT NULL,
    data text NOT NULL,
    flags integer NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, mailbox, uid)
);
CREATE INDEX rc_cache_messages_expires_idx ON rc_cache_messages (expires);
CREATE TABLE rc_dictionary (
    user_id integer DEFAULT NULL
        REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
   "language" varchar(5) NOT NULL,
    data text NOT NULL,
    CONSTRAINT dictionary_user_id_language_key UNIQUE (user_id, "language")
);
CREATE SEQUENCE rc_searches_seq
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
CREATE TABLE rc_searches (
    search_id integer DEFAULT nextval('rc_searches_seq'::text) PRIMARY KEY,
    user_id integer NOT NULL
        REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    "type" smallint DEFAULT 0 NOT NULL,
    name varchar(128) NOT NULL,
    data text NOT NULL,
    CONSTRAINT searches_user_id_key UNIQUE (user_id, "type", name)
);
CREATE TABLE rc_system (
    name varchar(64) NOT NULL PRIMARY KEY,
    value text
);
INSERT INTO rc_system (name, value) VALUES ('roundcube-version', '2015030800');
