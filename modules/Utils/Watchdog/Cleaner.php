<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Watchdog_Cleaner
{

    public static function instance()
    {
        static $instance;
        if (!$instance) {
            $instance = new self();
        }
        return $instance;
    }

    private function __construct() { }

    public function cron()
    {
        $inactive_users = $this->get_inactive_users();
        $clean_users = Cache::get('watchdog_clean_users');
        if ($clean_users) {
            if ($clean_users['time'] < (time() - 6 * 24 * 60 * 60)) { // 6 days
                foreach ($clean_users['users'] as $x) {
                    if (in_array($x, $inactive_users)) {
                        $this->cleanup_user($x);
                    }
                }
                $this->cleanup_leftover_events();
                $clean_users = null;
            }
        }
        if (!$clean_users) {
            Cache::set('watchdog_clean_users', array('time' => time(), 'users' => $inactive_users));
        }
    }

    public function cleanup_inactive_users()
    {
        foreach ($this->get_inactive_users() as $user) {
            $this->cleanup_user($user);
        }
        $this->cleanup_leftover_events();
    }

    public function cleanup_user($user)
    {
        $this->purge_notifications($user);
        $this->unsubscribe_from_all_items($user);
        $this->unsubscribe_from_all_categories($user);
    }

    public function get_inactive_users()
    {
        return DB::GetCol('SELECT id FROM user_login WHERE active=0');
    }

    public function purge_notifications($user_id)
    {
        DB::Execute('UPDATE utils_watchdog_subscription AS uws SET last_seen_event=(SELECT MAX(id) FROM utils_watchdog_event AS uwe WHERE uwe.internal_id=uws.internal_id AND uwe.category_id=uws.category_id) WHERE user_id=%d', array($user_id));
        DB::Execute('UPDATE utils_watchdog_subscription AS uws SET last_seen_event=-1 WHERE last_seen_event IS NULL');
    }

    public function unsubscribe_from_all_items($user_id)
    {
        DB::Execute('DELETE FROM utils_watchdog_subscription WHERE user_id=%d', array($user_id));
    }

    public function unsubscribe_from_all_categories($user_id)
    {
        DB::Execute('DELETE FROM utils_watchdog_category_subscription WHERE user_id=%d', array($user_id));
    }

    public function cleanup_leftover_events()
    {
        if (DB::is_mysql()) {
            DB::Execute('DELETE uwe.* FROM utils_watchdog_event AS uwe LEFT JOIN (SELECT internal_id, category_id, MIN(uws.last_seen_event) min FROM utils_watchdog_subscription AS uws GROUP BY uws.internal_id, uws.category_id) AS uws ON uws.internal_id = uwe.internal_id AND uws.category_id = uwe.category_id WHERE uwe.id < uws.min');
        } else {
            DB::Execute('DELETE FROM utils_watchdog_event AS uwe WHERE id < (SELECT MIN(last_seen_event) FROM utils_watchdog_subscription AS uws WHERE uws.internal_id = uwe.internal_id AND uws.category_id = uwe.category_id)');
        }
    }
}
