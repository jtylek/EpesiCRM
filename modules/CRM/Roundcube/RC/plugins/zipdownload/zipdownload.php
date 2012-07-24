<?php

/**
 * ZipDownload
 *
 * Plugin to allow the download of all message attachments in one zip file
 *
 * @version @package_version@
 * @requires php_zip extension (including ZipArchive class)
 * @author Philip Weir
 */
class zipdownload extends rcube_plugin
{
	public $task = 'mail';

	function init()
	{
		$rcmail = rcmail::get_instance();
		$this->load_config();
		$this->add_texts('localization');

		if ($rcmail->config->get('zipdownload_attachments', 1) > -1 && ($rcmail->action == 'show' || $rcmail->action == 'preview'))
			$this->add_hook('template_object_messageattachments', array($this, 'attachment_ziplink'));

		$this->register_action('plugin.zipdownload.zip_attachments', array($this, 'download_attachments'));
		$this->register_action('plugin.zipdownload.zip_messages', array($this, 'download_selection'));
		$this->register_action('plugin.zipdownload.zip_folder', array($this, 'download_folder'));

		if ($rcmail->config->get('zipdownload_folder', false) || $rcmail->config->get('zipdownload_selection', false)) {
			$this->include_script('zipdownload.js');
			$this->api->output->set_env('zipdownload_selection', $rcmail->config->get('zipdownload_selection', false));

			if ($rcmail->config->get('zipdownload_folder', false) && ($rcmail->action == '' || $rcmail->action == 'show')) {
				$zipdownload = $this->api->output->button(array('command' => 'plugin.zipdownload.zip_folder', 'type' => 'link', 'classact' => 'active', 'content' => $this->gettext('downloadfolder')));
				$this->api->add_content(html::tag('li', array('class' => 'separator_above'), $zipdownload), 'mailboxoptions');
			}
		}
	}

	function attachment_ziplink($p)
	{
		$rcmail = rcmail::get_instance();

		// only show the link if there is more than the configured number of attachments
		if (substr_count($p['content'], '<li>') > $rcmail->config->get('zipdownload_attachments', 1)) {
			$link = html::tag('li', null,
				html::a(array(
					'href' => rcmail_url('plugin.zipdownload.zip_attachments', array('_mbox' => $rcmail->output->env['mailbox'], '_uid' => $rcmail->output->env['uid'])),
					'title' => $this->gettext('downloadall'),
					),
					html::img(array('src' => $this->url(null) . $this->local_skin_path() . '/zip.png', 'alt' => $this->gettext('downloadall'), 'border' => 0)))
				);

			$p['content'] = preg_replace('/(<ul[^>]*>)/', '$1' . $link, $p['content']);
		}

		return $p;
	}

	function download_attachments()
	{
		$rcmail = rcmail::get_instance();
		$imap = $rcmail->imap;
		$temp_dir = $rcmail->config->get('temp_dir');
		$tmpfname = tempnam($temp_dir, 'attachments');
		$message = new rcube_message(get_input_value('_uid', RCUBE_INPUT_GET));

		// open zip file
		$zip = new ZipArchive();
		$zip->open($tmpfname, ZIPARCHIVE::OVERWRITE);

		foreach ($message->attachments as $part) {
			$pid = $part->mime_id;
			$part = $message->mime_parts[$pid];

			if ($part->body)
				$orig_message_raw = $part->body;
			else
				$orig_message_raw = $imap->get_message_part($message->uid, $part->mime_id, $part);

			$disp_name = $this->_convert_filename($part->filename, $part->charset, $rcmail->config->get('zipdownload_charset'));
			$zip->addFromString($disp_name, $orig_message_raw);
		}

		$zip->close();

		$browser = new rcube_browser;
		send_nocacheing_headers();

		$filename = ($message->subject ? $message->subject : 'roundcube') . '.zip';

		if ($browser->ie && $browser->ver < 7)
			$filename = rawurlencode(abbreviate_string($filename, 55));
		else if ($browser->ie)
			$filename = rawurlencode($filename);
		else
			$filename = addcslashes($filename, '"');

		// send download headers
		header("Content-Type: application/octet-stream");
		if ($browser->ie)
			header("Content-Type: application/force-download");

		// don't kill the connection if download takes more than 30 sec.
		@set_time_limit(0);
		header("Content-Disposition: attachment; filename=\"". $filename ."\"");
		header("Content-length: " . filesize($tmpfname));
		readfile($tmpfname);

		// delete zip file
		unlink($tmpfname);

		exit;
	}

	function download_selection()
	{
		if (isset($_REQUEST['_uid'])) {
			$uids = explode(",", $_REQUEST['_uid']);

			if (sizeof($uids) > 0)
				$this->_download_messages($uids);
		}
	}

	function download_folder()
	{
		$imap = rcmail::get_instance()->imap;
		$mbox_name = $imap->get_mailbox_name();

		// initialize searching result if search_filter is used
		if ($_SESSION['search_filter'] && $_SESSION['search_filter'] != 'ALL') {
			$search_request = md5($mbox_name.$_SESSION['search_filter']);
			$imap->search($mbox_name, $_SESSION['search_filter'], RCMAIL_CHARSET);
		}

		// fetch message headers for all pages
		$uids = array();
		if ($count = $imap->messagecount($mbox_name, $imap->threading ? 'THREADS' : 'ALL', FALSE)) {
			for ($i = 0; ($i * $imap->page_size) <= $count; $i++) {
				$a_headers = $imap->list_headers($mbox_name, ($i + 1));

				foreach ($a_headers as $n => $header) {
					if (empty($header))
						continue;

					array_push($uids, $header->uid);
				}
			}
		}

		if (sizeof($uids) > 0)
			$this->_download_messages($uids);
	}

	private function _download_messages($uids)
	{
		$rcmail = rcmail::get_instance();
		$imap = $rcmail->imap;
		$temp_dir = $rcmail->config->get('temp_dir');
		$tmpfname = tempnam($temp_dir, 'messages');

		// open zip file
		$zip = new ZipArchive();
		$zip->open($tmpfname, ZIPARCHIVE::OVERWRITE);

		foreach ($uids as $key => $uid){
			$message = $imap->get_raw_body($uid);
			$headers = $imap->get_headers($uid);
			$subject = $imap->decode_header($headers->subject);
			$subject = $this->_convert_filename($subject, RCMAIL_CHARSET, $rcmail->config->get('zipdownload_charset'));
			$subject = substr($subject, 0, 16);

			if (isset($subject) && $subject !="")
				$disp_name = $subject . ".eml";
			else
				$disp_name = "message_rfc822.eml";

			$disp_name = $uid . "_" . $disp_name;
			$zip->addFromString($disp_name, $message);
		}

		$zip->close();

		$browser = new rcube_browser;
		send_nocacheing_headers();

		$filename = $imap->get_mailbox_name() . '.zip';

		if ($browser->ie && $browser->ver < 7)
			$filename = rawurlencode(abbreviate_string($filename, 55));
		else if ($browser->ie)
			$filename = rawurlencode($filename);
		else
			$filename = addcslashes($filename, '"');

		// send download headers
		header("Content-Type: application/octet-stream");
		if ($browser->ie)
			header("Content-Type: application/force-download");

		// don't kill the connection if download takes more than 30 sec.
		@set_time_limit(0);
		header("Content-Disposition: attachment; filename=\"". $filename ."\"");
		header("Content-length: " . filesize($tmpfname));
		readfile($tmpfname);

		// delete zip file
		unlink($tmpfname);

		exit;
	}

	private function _convert_filename($str, $from = RCMAIL_CHARSET, $to = 'ISO-8859-1')
	{
		$str = rcube_charset_convert($str, $from, $to);
		return $str;
	}
}

?>