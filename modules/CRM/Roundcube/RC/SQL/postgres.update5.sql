ALTER TABLE "rc_users" ADD failed_login timestamp with time zone DEFAULT NULL;
ALTER TABLE "rc_users" ADD failed_login_counter integer DEFAULT NULL;
