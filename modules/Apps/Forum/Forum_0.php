<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage forum
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Forum extends Module {
	private $key = '';
	private $indicator = '';

	public function body() {
		$view_board = $this->get_module_variable('view_board',isset($_REQUEST['view_board'])?$_REQUEST['view_board']:null);
		if ($view_board) {
			if($this->view_board($view_board))
				return;
			else
				$this->unset_module_variable('view_board');
		}
				
		$ret = DB::Execute('SELECT id, name, descr FROM apps_forum_board');
		$boards = array();
		while ($row = $ret->FetchRow()) 
			$boards[] = array('descr' => $row['descr'],
				'label' => '<a '.$this->create_callback_href(array($this,'view_board'), array($row['id'])).'>'.$row['name'].'</a>',
				'delete' => Base_AclCommon::i_am_admin()?'<a '.$this->create_confirm_callback_href($this->ht('Are you sure you want to delete this board?'), array($this,'delete_board'), array($row['id'])).'>'.$this->t('Delete').'</a>':null
				);
		
		$theme = & $this->pack_module('Base/Theme');
		$theme -> assign('forum_boards',$this->t('Forum Boards'));
		$theme -> assign('boards',$boards);
		if (Base_AclCommon::i_am_admin()) {
			Base_ActionBarCommon::add('add',$this->ht('New board'),$this->create_callback_href(array($this,'add_board')));
		}
		$theme -> display('Boards');
		$this->indicator = '';
	}
	
	public function view_board($board){
		if ($this->is_back()) return false;
		$ret = DB::Execute('SELECT id, topic FROM apps_forum_thread WHERE apps_forum_board_id=%d',$board);
		$threads = array();

		while ($row = $ret->FetchRow()){
			$comment = & $this->init_module('Utils/Comment','apps_forum_'.$this->key.'_'.$row['id']);
			$posts = $comment->fetch_posts();
			$post_count = count($posts);
			if ($post_count==0) {
				$last_post = array('date'=>'---', 'user'=>'---');
				$time_int = '0000000000000000';
			} else {
				$last_post = $posts[$post_count-1];
				$time_int = str_pad(strtotime($last_post['date']), 16, "0", STR_PAD_LEFT);
			}
			$threads[$time_int.'_'.$row['id']] = 
				array(	'topic' => '<a '.$this->create_callback_href(array($this,'view_thread'),array($board,$row['id'])).'>'.$row['topic'].'</a>',
						'posted_on' =>  $this->t('Posted on %s',array($last_post['date'])),
						'posted_by' =>  $this->t('Posted by %s',array($last_post['user'])),
						'post_count' => $post_count?$post_count:'0',
						'delete' => Base_AclCommon::i_am_admin()?'<a '.$this->create_confirm_callback_href($this->ht('Are you sure you want to delete this thread?'),array($this,'delete_thread'),array($row['id'])).'>'.$this->t('Delete').'</a>':null
				);
		}
		krsort($threads);
		
		$theme = & $this->pack_module('Base/Theme');
		$theme -> assign('latest_post',$this->t('Latest post'));
		$theme -> assign('posts_count',$this->t('Posts'));
		$theme -> assign('topic',$this->t('Topic'));
		$theme -> assign('threads',$threads);
		$board_name = DB::GetOne('SELECT name FROM apps_forum_board WHERE id = %d',$board);
		$theme -> assign('board_name',$board_name);
		$this->indicator = ' board';
		$theme -> assign('forum_boards','<a '.$this->create_back_href().'>'.$this->t('Forum Boards').'</a>');
		Base_ActionBarCommon::add('back',$this->ht('Boards'),$this->create_back_href());
		if (Base_AclCommon::i_am_user())
			Base_ActionBarCommon::add('add',$this->ht('New thread'),$this->create_callback_href(array($this,'new_thread'),array($board)));
		$theme -> display('Threads');
		return true;
	}

	public function view_thread($board,$thread){
		if ($this->is_back()) return false;
		$board_name = DB::GetOne('SELECT name FROM apps_forum_board WHERE id = %d',$board);

		$comment = & $this->init_module('Utils/Comment','apps_forum_'.$this->key.'_'.$thread);
		
		$comment->set_moderator(Base_AclCommon::i_am_admin());
		$comment->set_per_page(20);
		if (!Base_AclCommon::i_am_user())
			$comment->set_reply(false);
		$comment->reply_on_comment_page(false);
		$comment->tree_structure(false);

		Base_ActionBarCommon::add('back',$board_name,$this->create_back_href());

//		ob_start();
		$posts = $this -> get_html_of_module($comment);	
//		$posts = ob_get_contents();
//		ob_end_clean();

		$theme = & $this->init_module('Base/Theme');

		$theme -> assign('posts',$posts);
		$topic_name = DB::GetOne('SELECT topic FROM apps_forum_thread WHERE id = %d',$thread);
		$theme -> assign('topic',$topic_name);
		$this->indicator = ' topic';
		$theme -> assign('forum_boards','<a '.$this->create_back_href(2).'>'.$this->t('Forum Boards').'</a>'); // TODO: need some way to get back to forum boards
		$theme -> assign('board_name','<a '.$this->create_back_href().'>'.$board_name.'</a>');
		$theme -> display('View_Thread');
		return true;
	}

	public function add_board(){
		if ($this->is_back()) return false;
		$this->indicator = ' - new board';

		$form = & $this->init_module('Libs/QuickForm',$this->t('Creating new board...'),'add_board_form');
		$form -> addElement('header',null,$this->t('Create new board'));
		$form -> addElement('text','name',$this->t('Name'));
		$form -> addRule('name', $this->t('Field required'), 'required');
		$form -> addElement('textarea','descr',$this->t('Description'));
		$submit = HTML_QuickForm::createElement('submit','submit',$this->ht('Create'));
		$cancel = HTML_QuickForm::createElement('button','cancel',$this->ht('Cancel'), $this->create_back_href());
		$form -> addGroup(array($submit,$cancel));
		if ($form->validate()) {
			DB::Execute('INSERT INTO apps_forum_board (name,descr) VALUES (%s,%s)',array(htmlspecialchars($form->exportValue('name'),ENT_QUOTES,'UTF-8'),htmlspecialchars($form->exportValue('descr'),ENT_QUOTES,'UTF-8')));
			return false;
		}
		$form->display();
		return true;
	}
	
	public function delete_board($board){
		$ret = DB::Execute('SELECT id FROM apps_forum_thread WHERE apps_forum_board_id=%d',$board);
		while ($row = $ret->FetchRow())
			$this->delete_thread($row['id']);
		DB::Execute('DELETE FROM apps_forum_board WHERE id=%d',$board);
		return false;
	}
	
	public function delete_thread($thread){
		DB::Execute('DELETE FROM apps_forum_thread WHERE id=%d',$thread);
		Utils_CommentCommon::delete_posts_by_topic('apps_forum_'.$this->key.'_'.$thread);
		return false;
	}

	public function new_thread($board){
		if ($this->is_back()) return false;

		$this->indicator = ' - new thread';

		$form = & $this->init_module('Libs/QuickForm',$this->ht('Creating new thread'));
		$theme = & $this->init_module('Base/Theme');

		$form -> addElement('hidden','post_content','none');
		$form -> addElement('text','topic',$this->t('Topic'));
		$form -> addRule('topic',$this->t('Field required'),'required');
		$form -> addElement('textarea','post',$this->t('First post'),array('rows'=>4,'cols'=>40,'onBlur'=>'document.getElementsByName(\'post_content\')[0].value = document.getElementsByName(\'post\')[0].value.replace(/\n/g,\'<br>\');'));
		$form -> addRule('post',$this->t('Field required'),'required');
		$form -> addElement('submit','submit','Submit');
		$form -> addElement('button','cancel','Cancel',$this->create_back_href());
		if ($form->validate() && Base_AclCommon::i_am_user()) {
			$topic = htmlspecialchars($form->exportValue('topic'),ENT_QUOTES,'UTF-8');
			DB::Execute('INSERT INTO apps_forum_thread (topic, apps_forum_board_id) VALUES (%s,%d)',array($topic,$board));
			$id = DB::GetOne('SELECT id FROM apps_forum_thread WHERE topic=%s AND apps_forum_board_id=%d',array($topic,$board));
			$comment = & $this->init_module('Utils/Comment','apps_forum_'.$this->key.'_'.$id);
			$comment->add_post($form->exportValue('post_content'));
			return false;
		}
		$form->assign_theme('form', $theme);

		$theme->assign('required', '<span align=top size=4 style="color:#FF0000">*</span>');
		$theme->assign('required_description', $this->t('Indicates required fields.'));
		$theme->assign('board_name','<a '.$this->create_back_href().'>'.DB::GetOne('SELECT name FROM apps_forum_board WHERE id = %d',$board).'</a>');
		$theme->assign('forum_boards','<a '.$this->create_back_href(2).'>'.$this->t('Forum Boards').'</a>');
		$theme->display('New_Thread');
		return true;
	}

	public function caption() {
		return 'Forum'.$this->indicator;
	}
}

?>