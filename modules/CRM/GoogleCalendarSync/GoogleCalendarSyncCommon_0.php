<?php
/**
 * Pushes each user's Epesi meetings to their own Google Calendar (one-way,
 * Epesi -> Google). Per-user OAuth, cron-polled. See
 * AI-shared/Epesi-Google-Calendar-sync.md for the design this was built from.
 *
 * Talks to Google directly over curl (OAuth2 token endpoint + the Calendar
 * v3 REST API) rather than the official google/apiclient SDK - that SDK's
 * google/apiclient-services dependency bundles PHP stubs for every Google
 * API (400MB+, 10,000+ files) with no way to pull in just Calendar, which
 * doesn't fit this app's "no build step" ethos for what's really a handful
 * of plain JSON/HTTPS calls. See the design doc's "no vendored dependency"
 * section for the full story.
 *
 * @author Claude Code
 * @copyright Janusz Tylek
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage GoogleCalendarSync
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

use Symfony\Component\HttpFoundation\RedirectResponse;

class CRM_GoogleCalendarSyncCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-calendar-check'; }

	const SYNC_WINDOW_PAST_DAYS = 7;
	const SYNC_WINDOW_FUTURE_DAYS = 180;
	const MAX_ACCOUNTS_PER_TICK = 20;

	const OAUTH_AUTHORIZE_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
	const OAUTH_TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
	const OAUTH_REVOKE_ENDPOINT = 'https://oauth2.googleapis.com/revoke';
	const USERINFO_ENDPOINT = 'https://www.googleapis.com/oauth2/v2/userinfo';
	const CALENDAR_API_BASE = 'https://www.googleapis.com/calendar/v3/';
	const OAUTH_SCOPE = 'https://www.googleapis.com/auth/calendar.events email';

	// ---- My Settings tile ----

	public static function user_settings() {
		if (!Base_AclCommon::check_permission('Google Calendar Sync')) return array();
		return array(__('Google Calendar Sync') => 'connect');
	}

	// ---- Admin config screen (installation-wide Client ID/Secret) ----

	public static function admin_caption() {
		return array('label'=>__('Google Calendar Sync'), 'section'=>__('Server Configuration'));
	}

	// Same shape as Base_EssClientCommon::admin_access() - a plain boolean
	// gate is enough to hide the whole tile from non-superadmins, no need
	// for per-section admin_access_levels().
	public static function admin_access() {
		return Base_AclCommon::i_am_sa();
	}

	public static function is_configured() {
		return (bool) Variable::get('crm_googlecalendarsync_client_id', false) && (bool) Variable::get('crm_googlecalendarsync_client_secret', false);
	}

	// ---- cron ----

	public static function cron() {
		return array('cron_sync' => 15);
	}

	// One cron.php invocation runs at most one due callback (see
	// modules/Utils/Watchdog/WatchdogCommon_0.php's own cron()) - this must
	// self-batch rather than assume it can loop over every account in one call.
	public static function cron_sync() {
		foreach (self::accounts_due_for_sync(self::MAX_ACCOUNTS_PER_TICK) as $account) {
			self::sync_account($account);
		}
	}

	// Never-synced accounts first, then oldest-synced - avoided ORDER BY
	// last_synced_on alone since MySQL and PostgreSQL disagree on whether
	// NULL sorts first or last in ASC order.
	private static function accounts_due_for_sync($limit) {
		$never = DB::GetAll('SELECT * FROM crm_googlecalendarsync_accounts WHERE enabled=1 AND last_synced_on IS NULL ORDER BY id ASC');
		$never = is_array($never) ? $never : array();
		if (count($never) >= $limit) return array_slice($never, 0, $limit);
		$synced = DB::GetAll('SELECT * FROM crm_googlecalendarsync_accounts WHERE enabled=1 AND last_synced_on IS NOT NULL ORDER BY last_synced_on ASC');
		$synced = is_array($synced) ? $synced : array();
		return array_merge($never, array_slice($synced, 0, $limit - count($never)));
	}

	// "Sync Now" button on the connect() status page - runs the same
	// sync_account() cron_sync() would eventually get to, immediately and
	// for just this one user, so the status page reflects the outcome
	// (last_synced_on/last_error) as soon as it re-renders.
	public static function sync_current_user() {
		$account = self::get_account(Acl::get_user());
		if ($account) self::sync_account($account);
	}

	public static function sync_account($account) {
		$user_id = (int) $account['epesi_user_id'];
		$contact = CRM_ContactsCommon::get_contact_by_user_id($user_id);
		if (!$contact) {
			self::mark_synced($account['id'], __('No matching Employee record for this Epesi user.'));
			return;
		}

		try {
			$access_token = self::access_token_for_account($account);
		} catch (Exception $e) {
			self::mark_synced($account['id'], $e->getMessage());
			return;
		}

		$calendar_id = $account['calendar_id'] ?: 'primary';

		$meetings = self::window_meetings($contact['id']);
		$current_ids = array_keys($meetings);

		$map_rows = DB::GetAll('SELECT * FROM crm_googlecalendarsync_map WHERE epesi_user_id=%d', array($user_id));
		$map_rows = is_array($map_rows) ? $map_rows : array();
		$map_by_meeting = array();
		foreach ($map_rows as $m) $map_by_meeting[$m['meeting_id']] = $m;

		$last_error = null;

		// Meetings no longer in scope (deleted, un-assigned from this
		// employee, or fallen outside the sync window) - remove the Google
		// event and the map row. 404/410 from Google just means it's
		// already gone, not an error worth surfacing.
		foreach ($map_rows as $m) {
			if (in_array($m['meeting_id'], $current_ids)) continue;
			try {
				self::delete_event($access_token, $calendar_id, $m['google_event_id']);
			} catch (Exception $e) {
				if (!in_array($e->getCode(), array(404, 410))) $last_error = $e->getMessage();
			}
			DB::Execute('DELETE FROM crm_googlecalendarsync_map WHERE id=%d', array($m['id']));
		}

		foreach ($meetings as $id => $meeting) {
			$hash = self::content_hash($meeting);
			$existing = $map_by_meeting[$id] ?? null;
			try {
				if (!$existing) {
					$created = self::insert_event($access_token, $calendar_id, self::build_google_event($meeting));
					DB::Execute('INSERT INTO crm_googlecalendarsync_map (meeting_id, epesi_user_id, google_event_id, content_hash, last_synced_on) VALUES (%d,%d,%s,%s,%T)',
						array($id, $user_id, $created['id'], $hash, time()));
				} elseif ($existing['content_hash'] !== $hash) {
					try {
						self::update_event($access_token, $calendar_id, $existing['google_event_id'], self::build_google_event($meeting));
					} catch (Exception $e) {
						// Event was deleted/expired on the Google side since we last synced - recreate it.
						if (!in_array($e->getCode(), array(404, 410))) throw $e;
						$created = self::insert_event($access_token, $calendar_id, self::build_google_event($meeting));
						DB::Execute('UPDATE crm_googlecalendarsync_map SET google_event_id=%s WHERE id=%d', array($created['id'], $existing['id']));
					}
					DB::Execute('UPDATE crm_googlecalendarsync_map SET content_hash=%s, last_synced_on=%T WHERE id=%d', array($hash, time(), $existing['id']));
				}
			} catch (Exception $e) {
				$code = $e->getCode();
				$last_error = $e->getMessage();
				if ($code == 401) { $last_error = __('Google authorization expired - please reconnect.'); break; }
				if ($code == 403 || $code == 429) break; // rate-limited/forbidden - back off, resume next tick
			}
		}

		self::mark_synced($account['id'], $last_error);
	}

	private static function mark_synced($account_id, $error) {
		DB::Execute('UPDATE crm_googlecalendarsync_accounts SET last_synced_on=%T, last_error=%s, updated_on=%T WHERE id=%d', array(time(), $error ?: null, time(), $account_id));
	}

	// Restricted to this contact's own meetings via the 'employees' crit
	// (multiselect containment, same shape crm_event_get()'s own
	// 'employees'=>$me['id'] default-value usage relies on) - cron runs as
	// SA (Base_AclCommon::set_sa_user()), so ACL filtering alone wouldn't
	// scope results to the right person. Window filtering is done in PHP
	// rather than via a crits date-range expression, cheap enough since
	// recurring meetings are single master rows here, never expanded.
	private static function window_meetings($contact_id) {
		$rows = Utils_RecordBrowserCommon::get_records('crm_meeting', array('employees' => $contact_id));
		$window_start = date('Y-m-d', strtotime('-'.self::SYNC_WINDOW_PAST_DAYS.' days'));
		$window_end = date('Y-m-d', strtotime('+'.self::SYNC_WINDOW_FUTURE_DAYS.' days'));
		$ret = array();
		foreach ($rows as $id => $meeting) {
			$recurring = ((int) $meeting['recurrence_type']) > 0;
			if ($recurring) {
				if ($meeting['date'] > $window_end) continue;
				if ($meeting['recurrence_end'] && $meeting['recurrence_end'] < $window_start) continue;
			} else {
				if ($meeting['date'] < $window_start || $meeting['date'] > $window_end) continue;
			}
			$ret[$id] = $meeting;
		}
		return $ret;
	}

	private static function content_hash($meeting) {
		return md5(serialize(array(
			(string) $meeting['title'], (string) $meeting['description'], (string) $meeting['date'], (string) $meeting['time'],
			(int) $meeting['duration'], (int) $meeting['recurrence_type'], (string) $meeting['recurrence_end'], (string) $meeting['recurrence_hash'],
			(int) $meeting['permission'],
		)));
	}

	// crm_meeting has no timezone field (naive local date+time) - events are
	// built using the server's own default timezone for every user, there's
	// nothing per-record to translate instead. Returns a plain array shaped
	// like the Calendar API v3 Event resource (json_encode-ready).
	private static function build_google_event($meeting) {
		$event = array('summary' => (string) $meeting['title']);
		if ($meeting['description']) $event['description'] = strip_tags((string) $meeting['description']);

		$timeless = ((int) $meeting['duration']) === -1;
		if ($timeless) {
			$event['start'] = array('date' => $meeting['date']);
			$event['end'] = array('date' => date('Y-m-d', strtotime($meeting['date'].' +1 day'))); // Google's end.date is exclusive
		} else {
			$tz = date_default_timezone_get();
			$start_ts = strtotime($meeting['date'].' '.$meeting['time']);
			$end_ts = $start_ts + max(0, (int) $meeting['duration']);
			$event['start'] = array('dateTime' => date('Y-m-d\TH:i:s', $start_ts), 'timeZone' => $tz);
			$event['end'] = array('dateTime' => date('Y-m-d\TH:i:s', $end_ts), 'timeZone' => $tz);
		}

		$rrule = self::build_rrule($meeting, $timeless);
		if ($rrule) $event['recurrence'] = array($rrule);

		return $event;
	}

	// recurrence_type: ''/0=none, 1..7=every N days, 8=custom weekdays via
	// recurrence_hash, 9=every 2 weeks, 10=monthly, 11=yearly - see
	// CRM_MeetingCommon::crm_event_get() for the reference expansion logic
	// this translates directly into an RRULE instead of expanding.
	private static function build_rrule($meeting, $timeless) {
		$type = (int) $meeting['recurrence_type'];
		if ($type <= 0) return null;
		if ($type <= 7) {
			$rule = 'FREQ=DAILY' . ($type > 1 ? ';INTERVAL='.$type : '');
		} elseif ($type == 8) {
			$days = array('MO','TU','WE','TH','FR','SA','SU'); // index 0=Monday matches date('N') used by crm_event_get()
			$hash = (string) $meeting['recurrence_hash'];
			$byday = array();
			foreach ($days as $i => $code) {
				if (isset($hash[$i]) && $hash[$i] !== '0' && $hash[$i] !== '') $byday[] = $code;
			}
			if (!$byday) return null;
			$rule = 'FREQ=WEEKLY;BYDAY=' . implode(',', $byday);
		} elseif ($type == 9) {
			$rule = 'FREQ=WEEKLY;INTERVAL=2';
		} elseif ($type == 10) {
			$rule = 'FREQ=MONTHLY';
		} elseif ($type == 11) {
			$rule = 'FREQ=YEARLY';
		} else {
			return null;
		}
		if ($meeting['recurrence_end']) {
			// UNTIL must match DTSTART's value type: plain YYYYMMDD for an
			// all-day event, YYYYMMDDTHHMMSSZ (UTC) for a timed one.
			if ($timeless) {
				$rule .= ';UNTIL=' . date('Ymd', strtotime($meeting['recurrence_end']));
			} else {
				$rule .= ';UNTIL=' . gmdate('Ymd\THis\Z', strtotime($meeting['recurrence_end'].' '.$meeting['time']));
			}
		}
		return 'RRULE:' . $rule;
	}

	// ---- Low-level HTTP (curl) ----

	// Throws only on a transport-level failure (DNS, TLS, timeout) - an HTTP
	// error status is returned normally so callers can inspect it.
	private static function http_request($method, $url, array $headers = array(), $body = null) {
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
		));
		if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		$response = curl_exec($ch);
		if ($response === false) {
			$err = curl_error($ch);
			curl_close($ch);
			throw new Exception(__('Could not reach Google: %s', array($err)));
		}
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return array('status' => $status, 'body' => $response);
	}

	// Extracts a human-readable message from either Google error shape:
	// OAuth token errors ({"error":"invalid_grant","error_description":"..."})
	// and Calendar API errors ({"error":{"code":404,"message":"Not Found"}}).
	private static function error_message($data, $status, $raw_body) {
		$err = $data['error'] ?? null;
		if (is_array($err)) return $err['message'] ?? json_encode($err);
		if (is_string($err)) return $err . (isset($data['error_description']) ? ': '.$data['error_description'] : '');
		return $raw_body !== '' ? $raw_body : ('HTTP '.$status);
	}

	private static function token_request(array $params) {
		$res = self::http_request('POST', self::OAUTH_TOKEN_ENDPOINT, array('Content-Type: application/x-www-form-urlencoded'), http_build_query($params));
		$data = json_decode($res['body'], true) ?: array();
		if ($res['status'] >= 400) throw new Exception(__('Google token error: %s', array(self::error_message($data, $res['status'], $res['body']))), $res['status']);
		return $data;
	}

	private static function calendar_request($access_token, $method, $path, $body = null) {
		$headers = array('Authorization: Bearer '.$access_token);
		$json_body = null;
		if ($body !== null) {
			$headers[] = 'Content-Type: application/json';
			$json_body = json_encode($body);
		}
		$res = self::http_request($method, self::CALENDAR_API_BASE.$path, $headers, $json_body);
		$data = $res['body'] !== '' ? json_decode($res['body'], true) : null;
		if ($res['status'] >= 400) throw new Exception(self::error_message((array) $data, $res['status'], $res['body']), $res['status']);
		return $data;
	}

	private static function insert_event($access_token, $calendar_id, array $event) {
		return self::calendar_request($access_token, 'POST', 'calendars/'.rawurlencode($calendar_id).'/events', $event);
	}

	private static function update_event($access_token, $calendar_id, $event_id, array $event) {
		return self::calendar_request($access_token, 'PUT', 'calendars/'.rawurlencode($calendar_id).'/events/'.rawurlencode($event_id), $event);
	}

	private static function delete_event($access_token, $calendar_id, $event_id) {
		self::calendar_request($access_token, 'DELETE', 'calendars/'.rawurlencode($calendar_id).'/events/'.rawurlencode($event_id));
	}

	private static function fetch_email($access_token) {
		try {
			$res = self::http_request('GET', self::USERINFO_ENDPOINT, array('Authorization: Bearer '.$access_token));
			if ($res['status'] >= 400) return null;
			$data = json_decode($res['body'], true);
			return $data['email'] ?? null;
		} catch (Exception $e) {
			return null; // best-effort only - a missing email never blocks the sync itself
		}
	}

	// ---- OAuth ----

	public static function get_account($user_id) {
		$r = DB::GetRow('SELECT * FROM crm_googlecalendarsync_accounts WHERE epesi_user_id=%d', array($user_id));
		return $r ?: null;
	}

	public static function authorize_url($redirect_uri) {
		$params = array(
			'client_id' => Variable::get('crm_googlecalendarsync_client_id', false),
			'redirect_uri' => $redirect_uri,
			'response_type' => 'code',
			'scope' => self::OAUTH_SCOPE,
			'access_type' => 'offline', // needed to reliably get a refresh_token back
			'prompt' => 'consent',
		);
		return self::OAUTH_AUTHORIZE_ENDPOINT . '?' . http_build_query($params);
	}

	// Deliberately NOT Module::create_ajax_callback_url() here: that bakes
	// the live per-tab CID into the URL (include/module.php's
	// get_ajax_callback_key()/create_ajax_callback_url()), which is fine for
	// a same-session ajax round-trip but wrong for an OAuth redirect_uri -
	// Google requires the exact same string both when requesting
	// authorization and when exchanging the code, and it needs to be
	// registerable ONCE in Google Cloud Console regardless of which
	// browser tab/session renders the Connect button. Same $func/$args pair
	// and a fixed cid=0 (ajax.php only requires it to be numeric, never
	// compares it against anything) keeps this byte-stable across renders.
	public static function oauth_redirect_uri() {
		$func = array('CRM_GoogleCalendarSyncCommon', 'oauth_callback');
		$key = md5(serialize($func).serialize(null));
		$_SESSION['ajax_callbacks'][$key] = array('callback'=>$func, 'args'=>null);
		return rtrim(get_epesi_url(), '/') . '/ajax.php?' . http_build_query(array('key'=>$key, 'cid'=>0));
	}

	// fully-qualified, not `use`-imported: CalendarCommon_0.php already imports Request into
	// the shared data/cache/common.php compilation unit (see module_manager.php's
	// create_common_cache()), and a duplicate `use` of the same class there is a PHP fatal.
	public static function oauth_callback(\Symfony\Component\HttpFoundation\Request $request, $args) {
		$home = rtrim(get_epesi_url(), '/') . '/';
		$code = $request->query->get('code');
		$user_id = Acl::get_user();
		if (!$code || !$user_id || !self::is_configured()) {
			return new RedirectResponse($home);
		}
		try {
			$token = self::token_request(array(
				'code' => $code,
				'client_id' => Variable::get('crm_googlecalendarsync_client_id', false),
				'client_secret' => Variable::get('crm_googlecalendarsync_client_secret', false),
				'redirect_uri' => self::oauth_redirect_uri(),
				'grant_type' => 'authorization_code',
			));
			$email = !empty($token['access_token']) ? self::fetch_email($token['access_token']) : null;
			self::save_account($user_id, $token, $email);
		} catch (Exception $e) {
			epesi_log("CRM_GoogleCalendarSync oauth_callback: ".$e->getMessage()."\n", 'cron.log');
		}
		return new RedirectResponse($home);
	}

	private static function save_account($user_id, $token, $email) {
		$access_enc = self::encrypt($token['access_token'] ?? '');
		$expires_ts = time() + (int) ($token['expires_in'] ?? 3600);
		$existing = self::get_account($user_id);
		if ($existing) {
			// Google only returns a refresh_token on the FIRST consent (or
			// when prompt=consent forces a new one, which it always does
			// here) - keep the old one if this exchange somehow didn't
			// include a fresh one rather than blanking it out.
			$refresh_enc = !empty($token['refresh_token']) ? self::encrypt($token['refresh_token']) : $existing['refresh_token_enc'];
			DB::Execute('UPDATE crm_googlecalendarsync_accounts SET google_email=%s, access_token_enc=%s, refresh_token_enc=%s, token_expires=%T, enabled=%b, last_error=NULL, updated_on=%T WHERE epesi_user_id=%d',
				array($email, $access_enc, $refresh_enc, $expires_ts, true, time(), $user_id));
		} else {
			DB::Execute('INSERT INTO crm_googlecalendarsync_accounts (epesi_user_id, google_email, access_token_enc, refresh_token_enc, token_expires, calendar_id, enabled, updated_on) VALUES (%d,%s,%s,%s,%T,%s,%b,%T)',
				array($user_id, $email, $access_enc, self::encrypt($token['refresh_token'] ?? ''), $expires_ts, 'primary', true, time()));
		}
	}

	// Returns a live access token for this account, refreshing it first if
	// expired. $account is passed by reference purely so a caller looping
	// over one account keeps seeing the fresh token_expires without a
	// second DB round-trip - the DB row itself is updated regardless.
	private static function access_token_for_account(&$account) {
		if (!self::is_configured()) throw new Exception(__('Google Calendar Sync is not configured.'));
		$access = self::decrypt($account['access_token_enc']);
		$refresh = self::decrypt($account['refresh_token_enc']);

		$expired = !$account['token_expires'] || strtotime($account['token_expires']) <= time();
		if (!$expired) return $access;

		if (!$refresh) throw new Exception(__('No refresh token on file - please reconnect Google Calendar Sync.'));
		$new_token = self::token_request(array(
			'refresh_token' => $refresh,
			'client_id' => Variable::get('crm_googlecalendarsync_client_id', false),
			'client_secret' => Variable::get('crm_googlecalendarsync_client_secret', false),
			'grant_type' => 'refresh_token',
		));
		$expires_ts = time() + (int) ($new_token['expires_in'] ?? 3600);
		DB::Execute('UPDATE crm_googlecalendarsync_accounts SET access_token_enc=%s, token_expires=%T WHERE id=%d', array(self::encrypt($new_token['access_token']), $expires_ts, $account['id']));
		$account['token_expires'] = date('Y-m-d H:i:s', $expires_ts);
		return $new_token['access_token'];
	}

	public static function disconnect($user_id) {
		$account = self::get_account($user_id);
		if (!$account) return;
		try {
			$token_plain = $account['refresh_token_enc'] ? self::decrypt($account['refresh_token_enc']) : self::decrypt($account['access_token_enc']);
			if ($token_plain) self::http_request('POST', self::OAUTH_REVOKE_ENDPOINT, array('Content-Type: application/x-www-form-urlencoded'), http_build_query(array('token'=>$token_plain)));
		} catch (Exception $e) {
			// best-effort revoke - local cleanup below still needs to happen either way
		}
		DB::Execute('DELETE FROM crm_googlecalendarsync_map WHERE epesi_user_id=%d', array($user_id));
		DB::Execute('DELETE FROM crm_googlecalendarsync_accounts WHERE epesi_user_id=%d', array($user_id));
	}

	public static function disconnect_current_user() {
		self::disconnect(Acl::get_user());
	}

	// ---- Per-user OAuth token encryption at rest ----
	//
	// No existing encrypted-secret precedent anywhere in core Epesi
	// (CRM_Mail's rc_accounts passwords are plaintext, masked only on
	// display) - this is new, minimal, and contained to this module rather
	// than a shared helper. Key is a random 32-byte file generated on first
	// use, stored under this module's own data dir (outside the DB,
	// data/ is gitignored).

	private static function get_encryption_key() {
		ModuleManager::create_data_dir('CRM_GoogleCalendarSync');
		$key_file = ModuleManager::get_data_dir('CRM_GoogleCalendarSync') . 'encryption.key';
		if (!file_exists($key_file)) {
			file_put_contents($key_file, random_bytes(32));
			@chmod($key_file, 0600);
		}
		return file_get_contents($key_file);
	}

	public static function encrypt($plain) {
		if ($plain === null || $plain === '') return '';
		$iv = random_bytes(12);
		$tag = '';
		$cipher = openssl_encrypt((string) $plain, 'aes-256-gcm', self::get_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
		if ($cipher === false) return '';
		return base64_encode($iv . $tag . $cipher);
	}

	public static function decrypt($encoded) {
		if (!$encoded) return '';
		$raw = base64_decode((string) $encoded, true);
		if ($raw === false || strlen($raw) < 28) return '';
		$iv = substr($raw, 0, 12);
		$tag = substr($raw, 12, 16);
		$cipher = substr($raw, 28);
		$plain = openssl_decrypt($cipher, 'aes-256-gcm', self::get_encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
		return $plain === false ? '' : $plain;
	}

}

?>
