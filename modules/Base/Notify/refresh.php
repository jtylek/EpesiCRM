<?php
/**
 * 
 * @author Georgi Hristov <ghristov@gmx.de>
 * @copyright Copyright &copy; 2014, Xoff Software GmbH
 * @license MIT
 * @version 2.0
 * @package epesi-notify
 * 
 */

define('CID', false);
define('READ_ONLY_SESSION', true);
require_once('../../../include.php');

/*
 * Early-out before ModuleManager::load_modules().
 *
 * The browser polls this endpoint on Base_NotifyCommon::refresh_rate (30s), and the
 * server-side rate limit below (is_refresh_due()) rejects anything that arrives sooner -
 * but it used to do so only *after* loading all ~95 installed modules, ~150 files. A poll
 * that returns nothing cost a full application bootstrap; measured 2026-08-31, this
 * endpoint was 8 requests / 637ms of a short session, the second-largest cost on the page
 * after process.php itself. Extra tabs, reloads and clock drift all multiply it.
 *
 * This reproduces just enough of get_session_token() + is_refresh_due() to prove a poll is
 * too early, using only the session and one query - no module classes needed.
 *
 * Deliberately fail-open: it only exits when it can *positively* show the poll is early.
 * If anything does not line up - no session user, no session id, no matching base_notify
 * row - it falls through to the original path unchanged, which then decides for real.
 *
 * Two shapes of row have to be considered, matching get_session_token():
 *   - default:        one row per session, keyed by md5(user_id . '__' . session_id)
 *   - one_cache mode: one row per user, found by single_cache_uid; every session of that
 *                     user shares it, so the derived token matches only the session that
 *                     happened to create the row
 * Matching either, and taking the newest, covers both without having to read the
 * one_cache user setting (which would mean loading modules - the thing being avoided).
 *
 * telegram=0 is essential, not incidental: telegram rows also carry single_cache_uid but
 * run on refresh_rate_telegram (300s), so letting one match here would answer this
 * poller's question with the wrong cycle's timestamp.
 *
 * Utils/RecordBrowser/indexer.php already uses this shape (an mtime guard ahead of
 * load_modules()); this is the same idea applied to the busier poller.
 */
if (!empty($_SESSION['user']) && session_id()) {
    $probe_token = md5($_SESSION['user'] . '__' . session_id());
    $last_refresh = DB::GetOne(
        'SELECT MAX(last_refresh) FROM base_notify WHERE telegram=0 AND (token=%s OR single_cache_uid=%d)',
        array($probe_token, $_SESSION['user'])
    );
    // Base_NotifyCommon::refresh_rate is 30; not readable without loading the module, so
    // it is restated here. Keep the two in sync - a mismatch only costs an extra full
    // bootstrap (too low) or one skipped poll cycle (too high), never a wrong answer.
    if (is_numeric($last_refresh) && time() < $last_refresh + 30) exit();
}

ModuleManager::load_modules();

ob_start();
$token = Base_NotifyCommon::get_session_token(); // will check is user logged

if ($token === false) {
    exit();
}

if (Base_NotifyCommon::is_disabled()) {	
	echo json_encode(array('disable'=>1));
	
	exit();
}

if (!Base_NotifyCommon::is_refresh_due($token)) exit();

$ret = array();
$message_count = 0;
$notified_cache = array();
	
$group_similar = Base_NotifyCommon::group_similar();
$refresh_time = time();
$notifications = Base_NotifyCommon::get_notifications($token);
$all_notified = true;

foreach ($notifications as $module => $module_new_notifications) {
	$timeout = Base_NotifyCommon::get_module_setting($module);

	if ($group_similar && count($module_new_notifications) > 1) {
		$message_count++;
		if ($message_count>Base_NotifyCommon::message_refresh_limit) break;

		$notified_cache[$module] = array_keys($module_new_notifications);
			
		$title = EPESI.' '.Base_NotifyCommon::get_module_caption($module);
		$body = __('%d new notifications', array(count($module_new_notifications)));
		$icon = Base_NotifyCommon::get_icon($module);
	
		$ret[] = array('title'=>$title, 'opts'=>array('body'=>$body, 'icon'=>$icon), 'timeout'=>$timeout);
	}
	else {	
		foreach ($module_new_notifications as $id=>$message) {
			$message_count++;
			if ($message_count>Base_NotifyCommon::message_refresh_limit) break 2;

			$notified_cache[$module][] = $id;
			
			$title = EPESI.' '.Base_NotifyCommon::strip_html($message['title']);
			$body = Base_NotifyCommon::strip_html($message['body']);
			$icon = Base_NotifyCommon::get_icon($module, $message);
	
			$ret[] = array('title'=>$title, 'opts'=>array('body'=>$body, 'icon'=>$icon, 'tag'=>$id), 'timeout'=>$timeout);
		}
	}

	$all_notified &= count($module_new_notifications) == count($notified_cache[$module]);
}

Base_NotifyCommon::set_notified_cache($notified_cache, $token, $all_notified ? $refresh_time : Base_NotifyCommon::get_last_refresh($token));

ob_end_clean();

if (count($ret)) {
    echo json_encode(array('messages' => $ret));
}

exit();
