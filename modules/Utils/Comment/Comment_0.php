<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-utils
 * @subpackage comment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Comment extends Module{
	private $qty;
	private $key = null;
	private $offset;
	private $per_page = 10;
	private $mod = false;
	private $report = false;
	private $reply = true;
	private $reply_on_comment_page = true;
	private $tree_structure = true;
	
	/**
	 * Constructs new instance of Comment module.
	 * Key specifies group of comments that will be operated with this instance.
	 * 
	 * @param string identifier of the comment group
	 */
	public function construct($key) {
		if(isset($key)) $this->key = $key;
		else trigger_error('Key not given to comment module, aborting',E_USER_ERROR);
	}
	
	/**
	 * Displays Comments.
	 */
	public function body(){
		if (!Base_AclCommon::i_am_user()) $this->reply=false;
		$action = $this->get_module_variable_or_unique_href_variable('action');
		if ($action) {
			$this->$action();
			return;
		}
		
		$form = $this->init_module('Libs/QuickForm',__('Posting reply'));
		$theme = $this->init_module('Base/Theme');

		if ($this->tree_structure) {
			$answer = $this->get_module_variable('answer',-1);
			$change_answer = $this -> get_unique_href_variable('answer');
			if ($change_answer) $answer = $change_answer;
			$this->set_module_variable('answer',$answer);
		} else $answer=-1;

		$report = $this -> get_unique_href_variable('report');
		if ($report) {
			DB::Execute('INSERT INTO comment_report (id, user_login_id) VALUES (%d, %d)',array($report,Acl::get_user()));
		}
		
		if ($this->reply)
			if ($this->reply_on_comment_page) {
				$form -> addElement('hidden','comment_content','none');
				if ($answer==-1) $form -> addElement('header','reply',__('Post in this thread'));
				else {
					$comment_info = DB::Execute('SELECT c.id, c.text, ul.login, c.created_on FROM comment AS c LEFT JOIN user_login AS ul ON (c.user_login_id = ul.id) WHERE c.id = %d ORDER BY created_on',array($answer))->FetchRow();
					$form -> addElement('header','reply',sprintf(__('Reply to %s\'s comment given at %s'),$comment_info['login'],date('G:i, d M Y',strtotime($comment_info['created_on']))));
					$form -> addElement('static','whole','','<a '.$this->create_unique_href(array('answer'=>-1)).'>'.__('Comment whole thread').'</a>');
				}
				$form -> addElement('textarea','comment_page_reply',__('Message'),array('rows'=>4,'cols'=>40));//,'onBlur'=>'document.getElementsByName(\'comment_content\')[0].value = document.getElementsByName(\'comment_page_reply\')[0].value.replace(/\n/g,\'<br>\');'));
				$form -> addElement('submit','submit_comment',__('Submit'));
				if ($form->validate() && $this->reply){
					$this->add_post($form->exportValue('comment_page_reply'),$answer);
					$this->unset_module_variable('answer');
					$answer = -1;
				}
				$form->assign_theme('form', $theme);
			} else {
				Base_ActionBarCommon::add('add',__('Reply'),$this->create_unique_href(array('action'=>'post_reply')));
			}

		$recordSet = DB::Execute('SELECT COUNT(*) FROM comment WHERE topic=%s AND parent <= -1',array($this->key))->FetchRow();

		if (!$this->per_page) $this->per_page = 10;
		$this->qty = $recordSet[0];

		$this->offset=intval($this->get_module_variable_or_unique_href_variable('offset'));
		if($this->get_unique_href_variable('next')=='1')
			$this->offset += $this->per_page;
		elseif($this->get_unique_href_variable('prev')=='1') {
			$this->offset -= $this->per_page;
			if($this->offset<0) $this->offset=0;
		}
		elseif($this->get_unique_href_variable('first')=='1')
			$this->offset = 0;
		elseif($this->get_unique_href_variable('last')=='1')
			$this->offset = floor(($this->qty-1)/$this->per_page)*$this->per_page;
		elseif(($goto = $this->get_unique_href_variable('goto',null)) !== null)
			$this->offset = $goto*$this->per_page;
		$this->set_module_variable('offset', $this->offset);

		$comments = $this->fetch_posts();

		$pages = ceil($this->qty/$this->per_page);
		$curr_page = $this->offset/$this->per_page+1;
		$i = 1;
		$before_dots = false;
		$after_dots = false;
		$pages_links = array(__('Pages'));
		while ($i <= $pages){
			if ($i==$curr_page) $pages_links[] = '<a>'.$i.'</a>'; // TODO: style and _link open possible solution, will need revision later
			else {
				if ($i<=3 || abs($i-$curr_page)<=1 || $pages-$i<2) $pages_links[] = '<a '.$this->create_unique_href(array('goto'=>($i-1))).'>'.$i.'</a>';
				else if ($i<$curr_page) {
					if (!$before_dots) {
						$before_dots = true;
						$pages_links[] = '...';
					}
				} else if (!$after_dots) {
					$after_dots = true;
					$pages_links[] = '...';
				}
			}
			$i++;
		}
		if (!empty($comments)) {
			$theme -> assign('comments', $comments);
			$theme -> assign('first', ($pages != $curr_page)?$this->first():null);
			$theme -> assign('prev', ($pages != $curr_page)?$this->prev():null);
			$theme -> assign('next', ($pages != $curr_page)?$this->next():null);
			$theme -> assign('last', ($pages != $curr_page)?$this->last():null);
			$theme -> assign('pages', ($pages != $curr_page)?$pages_links:null);
			$theme -> assign('no_comments',false);
		} else
			$theme->assign('no_comments','No comments yet.');

		$theme -> display('Comment');
	}
	
	/**
	 * Returns all comments from current (specified during construction) comment group.
	 * The result is an array. Each field in this array represents one comment.
	 * Comment is described with an array with following fields:
	 * text - comment contents
	 * user - username of a user that posted this comment
	 * date - date when comment was posted in format 'G:i, d M Y'
	 * report - link that allows to report this comment
	 * delete - link that allows to delete this comment
	 * reply - link that will switch 'reply to' to this comment
	 * tabs - number of tabs that are used to represent comment replies
	 * 
	 * @return array all comments
	 */
	public function fetch_posts(){
		$recordSet = DB::Execute('SELECT c.id, c.text, ul.login, c.created_on,c.parent FROM comment AS c LEFT JOIN user_login AS ul ON (c.user_login_id = ul.id) WHERE topic=%s AND parent <= -1 ORDER BY created_on',array($this->key));
		$comments = array();
		while (!$recordSet->EOF){
			$row = $recordSet->FetchRow();
			$this->prepare_comment($comments,$row);
		}
		return $comments;
	}
	
	private function prepare_comment(& $comments,$row,$tab = 0){
		$row['text'] = str_replace("\n",'<br>',htmlspecialchars($row['text']));
		if (Base_AclCommon::i_am_user()) {
			if ($this->mod) {
				$delete = '<a '.$this->create_confirm_callback_href(__('Are you sure you want to delete this post?'),array('Utils_CommentCommon','delete_post'),$row['id']).'>'.__('Delete').'</a>';
				$rep_count = DB::GetOne('SELECT COUNT(*) FROM comment_report WHERE id=%d',$row['id']);
				if (!$rep_count) $report = '';
				else $report = __('Reported %d time(s)',$rep_count);
			} else if ($this->report) {
				$rep_count = DB::GetOne('SELECT COUNT(*) FROM comment_report WHERE id=%d AND user_login_id=%d',array($row['id'],Acl::get_user()));
				if ($rep_count==0) $report = '<a '.$this->create_unique_href(array('report'=>$row['id'])).'>'.__('Report').'</a>';
				else $report = __('Post reported');
			}
		}
		$reply_vars = array('answer'=>$row['id']);
		if (!$this->reply_on_comment_page) $reply_vars['action'] = 'post_reply';
		if ($this->tree_structure && $this->reply) $reply_link = '<a '.$this->create_unique_href($reply_vars).'>'.__('Reply').'</a>';
		else $reply_link = null; 
		$comments[] = array('text'=>$row['text'],
							'user'=>$row['login'],
							'date'=>date('G:i, d M Y',strtotime($row['created_on'])),
							'report'=>isset($report)?$report:null,
							'delete'=>isset($delete)?$delete:null,
							'reply'=>$reply_link,
							'tabs'=>$tab);
		if ($row['parent']!=-1){
			$recordSet = DB::Execute('SELECT c.id, c.text, ul.login, c.created_on FROM comment AS c LEFT JOIN user_login AS ul ON (c.user_login_id = ul.id) WHERE parent = %d ORDER BY created_on',$row['id']);
			while (!$recordSet->EOF){
				$row = $recordSet->FetchRow();
				$this->prepare_comment($comments,$row,$tab+1);
			}			
		}
	}
	
	/**
	 * Displays and processes post replying form.
	 */
	public function post_reply(){
		if ($this->is_back()) {
			$this->unset_module_variable('action');
			$this->unset_module_variable('answer');
			location(array());
		}

		$form = $this->init_module('Libs/QuickForm',__('Posting reply'));
		$theme = $this->init_module('Base/Theme');

		if ($this->tree_structure) {
			$answer = $this->get_module_variable_or_unique_href_variable('answer',-1);
			$this->set_module_variable('answer',$answer);
		} else $answer=-1;

		$form -> addElement('hidden','comment_content','none');
		if ($answer!=-1) {
			$comment_info = DB::Execute('SELECT c.id, c.text, ul.login, c.created_on FROM comment AS c LEFT JOIN user_login AS ul ON (c.user_login_id = ul.id) WHERE c.id = %d ORDER BY created_on',array($answer))->FetchRow();
			$form -> addElement('header','reply',__('Reply to %s\'s comment given at %s',array($comment_info['login'],date('G:i, d M Y',strtotime($comment_info['created_on'])))));
		}
		$form -> addElement('textarea','comment_page_reply',__('Message'),array('rows'=>4,'cols'=>40));//,'onBlur'=>'document.getElementsByName(\'comment_content\')[0].value = document.getElementsByName(\'comment_page_reply\')[0].value.replace(/\n/g,\'<br>\');'));
		$form -> addRule('comment_page_reply',__('Field required'),'required');
		$form -> addElement('submit','submit_comment',__('Submit'));
		$form -> addElement('button','cancel_comment',__('Cancel'),$this->create_back_href());
		if ($form->validate() && $this->reply){
			$this->add_post($form->exportValue('comment_page_reply'),$answer);
			$this->unset_module_variable('answer');
			$this->unset_module_variable('action');
			$answer = -1;
			location(array());
		} else {
			$form->assign_theme('form', $theme);
			$theme->assign('required', '<span align=top size=4 style="color:#FF0000">*</span>');
			$theme->assign('required_description', __('Indicates required fields.'));
			$theme -> display('Reply');
		}
	}
	
	/**
	 * Adds new comment to current comment group.
	 * You can also specify to which comment this was reply to.
	 * 
	 * @param string text message
	 * @param integer id of a comment to which this one replies
	 */
	public function add_post($post_text, $answer_to=-1){
//		$post_text = str_replace("\n",'<br>',$post_text);
		DB::Execute('INSERT INTO comment (text, user_login_id, topic, created_on, parent) VALUES (%s, %d, %s, %s, %d)',array($post_text,Acl::get_user(),$this->key,date('Y-m-d G:i:s'),$answer_to));
	}

	private function first() {
		if($this->offset>0)
			return '<a '.$this->create_unique_href(array('first'=>1)).'>'.__('First').'</a>';
		else
			return null;
	} 
	
	private function prev() {
		if($this->offset>0)
    		return '</a><a '.$this->create_unique_href(array('prev'=>1)).'>'.__('Prev').'</a>';
		else
			return null;
	}
	
	private function next() {
		if($this->offset+$this->per_page<$this->qty) 
      		return '<a '.$this->create_unique_href(array('next'=>1)).'>'.__('Next').'</a>';
		else
			return null;
	}
	
	private function last() {
		if($this->offset+$this->per_page<$this->qty) 
      		return '<a '.$this->create_unique_href(array('last'=>1)).'>'.__('Last').'</a>';
		else
			return null;
	}

	/**
	 * Sets whether moderation options are available.
	 * False by default.
	 *  
	 * @param bool true to enable moderation
	 */
	public function set_moderator($mod){
		$this->mod = $mod;
	}

	/**
	 * Sets how many comments should be displayed on the page.
	 * 10 by default.
	 * 
	 * @param integer number of comments per page
	 */
	public function set_per_page($pp){
		$this->per_page = $pp;
	}

	/**
	 * Sets whether user is allowed to reply.
	 * True by default.
	 * 
	 * @param bool true to allow replying
	 */
	public function set_reply($r){
		$this->reply = $r;
	}

	/**
	 * Sets what method of posting comments should be used.
	 * True by default.
	 * 
	 * @param bool true to reply on comment page, false to place button on ActionBar
	 */
	public function reply_on_comment_page($rocp){
		$this->reply_on_comment_page = $rocp;
	}

	/**
	 * Sets whether replying to specific comment is allowed.
	 * True by default.
	 * 
	 * @param bool true to enable tree structure
	 */
	public function tree_structure($ts){
		return $this->tree_structure = $ts;
	}
}

?>
