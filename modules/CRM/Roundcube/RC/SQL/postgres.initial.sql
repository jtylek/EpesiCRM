CREATE SEQUENCE rc_user_ids
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
CREATE TABLE rc_users (
    user_id integer DEFAULT nextval('rc_user_ids'::text) PRIMARY KEY,
    username varchar(128) DEFAULT '' NOT NULL,
    mail_host varchar(128) DEFAULT '' NOT NULL,
    alias varchar(128) DEFAULT '' NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    last_login timestamp with time zone DEFAULT NULL,
    "language" varchar(5),
    preferences text DEFAULT ''::text NOT NULL,
    CONSTRAINT users_username_key UNIQUE (username, mail_host)
);
CREATE INDEX rc_users_alias_id_idx ON rc_users (alias);
CREATE TABLE "rc_session" (
    sess_id varchar(40) DEFAULT '' PRIMARY KEY,
    created timestamp with time zone DEFAULT now() NOT NULL,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    ip varchar(41) NOT NULL,
    vars text NOT NULL
);
CREATE INDEX rc_session_changed_idx ON rc_session (changed);
CREATE SEQUENCE rc_identity_ids
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
CREATE TABLE rc_identities (
    identity_id integer DEFAULT nextval('rc_identity_ids'::text) PRIMARY KEY,
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
CREATE SEQUENCE rc_contact_ids
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
CREATE TABLE rc_contacts (
    contact_id integer DEFAULT nextval('rc_contact_ids'::text) PRIMARY KEY,
    user_id integer NOT NULL
	REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    changed timestamp with time zone DEFAULT now() NOT NULL,
    del smallint DEFAULT 0 NOT NULL,
    name varchar(128) DEFAULT '' NOT NULL,
    email varchar(255) DEFAULT '' NOT NULL,
    firstname varchar(128) DEFAULT '' NOT NULL,
    surname varchar(128) DEFAULT '' NOT NULL,
    vcard text
);
CREATE INDEX rc_contacts_user_id_idx ON rc_contacts (user_id, email);
CREATE SEQUENCE rc_contactgroups_ids
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
CREATE TABLE rc_contactgroups (
    contactgroup_id integer DEFAULT nextval('rc_contactgroups_ids'::text) PRIMARY KEY,
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
CREATE SEQUENCE rc_cache_ids
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
CREATE TABLE "rc_cache" (
    cache_id integer DEFAULT nextval('rc_cache_ids'::text) PRIMARY KEY,
    user_id integer NOT NULL
	REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    cache_key varchar(128) DEFAULT '' NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    data text NOT NULL
);
CREATE INDEX rc_cache_user_id_idx ON "rc_cache" (user_id, cache_key);
CREATE INDEX rc_cache_created_idx ON "rc_cache" (created);
CREATE SEQUENCE rc_message_ids
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
CREATE TABLE rc_messages (
    message_id integer DEFAULT nextval('rc_message_ids'::text) PRIMARY KEY,
    user_id integer NOT NULL
	REFERENCES rc_users (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    del smallint DEFAULT 0 NOT NULL,
    cache_key varchar(128) DEFAULT '' NOT NULL,
    created timestamp with time zone DEFAULT now() NOT NULL,
    idx integer DEFAULT 0 NOT NULL,
    uid integer DEFAULT 0 NOT NULL,
    subject varchar(128) DEFAULT '' NOT NULL,
    "from" varchar(128) DEFAULT '' NOT NULL,
    "to" varchar(128) DEFAULT '' NOT NULL,
    cc varchar(128) DEFAULT '' NOT NULL,
    date timestamp with time zone NOT NULL,
    size integer DEFAULT 0 NOT NULL,
    headers text NOT NULL,
    structure text
);
ALTER TABLE rc_messages ADD UNIQUE (user_id, cache_key, uid);
CREATE INDEX rc_messages_index_idx ON rc_messages (user_id, cache_key, idx);
CREATE INDEX rc_messages_created_idx ON rc_messages (created);
