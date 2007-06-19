<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 0.9
 * @package apps-forum
 * @licence SPL
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Forum extends Module {
	private $lang;
	private $key = '';

	public function body($arg) {
		$this->lang = & $this->pack_module('Base/Lang');
		
		
		if (!Base_AclCommon::i_am_user())
			print($this->lang->t('Log in to the system to use forum.'));
			
		$action = $this->get_module_variable_or_unique_href_variable('action');
		if ($_REQUEST['action']) $action = $_REQUEST['action'];
		if ($action == '__NONE__') unset($action);
		$this->set_module_variable('action',$action);
		if ($action) {
			$this->$action();
			return;
		}
			
		$ret = DB::Execute('SELECT id, name, descr FROM apps_forum_board');
		$boards = array();
		while ($row = $ret->FetchRow()) $boards[] = array(	'descr' => $row['descr'],
															'label' => '<a '.$this->create_href(array('action'=>'view_board','board'=>$row['id'])).'>'.$row['name'].'</a>',
															'delete' => '<a '.$this->create_confirm_unique_href($this->lang->ht('Are you sure you want to delete this board?'),array('action'=>'delete_board','board'=>$row['id'])).'>'.$this->lang->t('Delete').'</a>'
															);
		
		$theme = & $this->pack_module('Base/Theme');
		$theme -> assign('forum_boards',$this->lang->t('Forum Boards'));
		$theme -> assign('boards',$boards);
		if (Base_AclCommon::i_am_admin()) {
			Base_ActionBarCommon::add_icon('add',$this->lang->ht('New board'),$this->create_unique_href(array('action'=>'add_board')));
		}
		$theme -> display('Boards');
	}
	
	public function add_board(){
		if ($this->is_back()){
			$this->unset_module_variable('action');
			location(array());
		}
		$form = & $this->init_module('Libs/QuickForm',$this->lang->t('Creating new board...',true),'add_board');
		$form -> addElement('header',null,$this->lang->t('Create new board'));
		$form -> addElement('text','name',$this->lang->t('Name'));
		$form -> addRule('name', $this->lang->t('Field required'), 'required');
		$form -> addElement('textarea','descr',$this->lang->t('Description'));
		$submit = HTML_QuickForm::createElement('submit','submit',$this->lang->ht('Create'));
		$cancel = HTML_QuickForm::createElement('button','cancel',$this->lang->ht('Cancel'), $this->create_back_href());
		$form -> addGroup(array($submit,$cancel));
		if ($form->validate()) {
			DB::Execute('INSERT INTO apps_forum_board (name,descr) VALUES (%s,%s)',array($form->exportValue('name'),$form->exportValue('descr')));
			$this->unset_module_variable('action');
			location(array());
		} else $form->display();
	}
	
	public function delete_board(){
		$board = $this->get_unique_href_variable('board'); 
		if ($board) {
			$ret = DB::Execute('SELECT id FROM apps_forum_thread WHERE apps_forum_board_id=%d',$board);
			while ($row = $ret->FetchRow())
				$this->delete_thread($row['id']);
			DB::Execute('DELETE FROM apps_forum_board WHERE id=%d',$board);
		}
		$this->set_module_variable('action','__NONE__');
		location(array());
	}
	
	public function delete_thread($thread=null){
		if (!$thread) $thread = $this->get_unique_href_variable('thread'); 
		if ($thread){
			DB::Execute('DELETE FROM apps_forum_thread WHERE id=%d',$thread);
			Utils_Comment::delete_posts_by_topic('apps_forum_'.$this->key.'_'.$thread);
		}
		$this->set_module_variable('action','view_board');
		location(array());
	}

	public function view_board(){
		$board = $this->get_module_variable('board');
		if ($_REQUEST['board']) $board = $_REQUEST['board'];
		$this->set_module_variable('board',$board);
		$ret = DB::Execute('SELECT id, topic FROM apps_forum_thread WHERE apps_forum_board_id=%d',$board);
		$threads = array();

		while ($row = $ret->FetchRow()){
			$comment = & $this->init_module('Utils/Comment','apps_forum_'.$this->key.'_'.$row['id']);
			$posts = $comment->fetch_posts();
			$post_count = count($posts);
			$last_post = $posts[$post_count-1];
			$threads[str_pad(strtotime($last_post['date']), 16, "0", STR_PAD_LEFT).'_'.$row['id']] = 
				array(	'topic' => '<a '.$this->create_href(array('action'=>'view_thread','thread'=>$row['id'])).'>'.$row['topic'].'</a>',
						'posted_on' =>  $this->lang->t('Posted on %s',$last_post['date']),
						'posted_by' =>  $this->lang->t('Posted by %s',$last_post['user']),
						'post_count' => $post_count?$post_count:'0',
						'delete' => '<a '.$this->create_confirm_unique_href($this->lang->ht('Are you sure you want to delete this thread?'),array('action'=>'delete_thread','thread'=>$row['id'])).'>'.$this->lang->t('Delete').'</a>'
				);
		}
		krsort($threads);
		
		$theme = & $this->pack_module('Base/Theme');
		$theme -> assign('latest_post',$this->lang->t('Latest post'));
		$theme -> assign('posts_count',$this->lang->t('Posts'));
		$theme -> assign('topic',$this->lang->t('Topic'));
		$theme -> assign('threads',$threads);
		$theme -> assign('board_name',DB::GetOne('SELECT name FROM apps_forum_board WHERE id = %d',$board));
		$theme -> assign('forum_boards','<a '.$this->create_unique_href(array('action'=>'__NONE__')).'>'.$this->lang->t('Forum Boards').'</a>');
		Base_ActionBarCommon::add_icon('back',$this->lang->ht('Boards'),$this->create_unique_href(array('action'=>'__NONE__')));
		Base_ActionBarCommon::add_icon('add',$this->lang->ht('New thread'),$this->create_unique_href(array('action'=>'new_thread','board'=>$board)));
		$theme -> display('Threads');
	}

	public function new_thread(){
		if ($this->is_back()){
			$this->set_module_variable('action','view_board');
			location(array());
		}

		$board = $this->get_module_variable('board');
		if ($_REQUEST['board']) $board = $_REQUEST['board'];
		$this->set_module_variable('board',$board);
				
		if ($this->is_back()) {
			$this->set_module_variable('view_board');
			location(array());
		}

		$form = & $this->init_module('Libs/QuickForm',$this->lang->ht('Creating new thread'));
		$theme = & $this->init_module('Base/Theme');

		$form -> addElement('hidden','post_content','none');
		$form -> addElement('text','topic',$this->lang->t('Topic'));
		$form -> addRule('topic',$this->lang->t('Field required'),'required');
		$form -> addElement('textarea','post',$this->lang->t('First post'),array('rows'=>4,'cols'=>40,'onBlur'=>'document.getElementsByName(\'post_content\')[0].value = document.getElementsByName(\'post\')[0].value.replace(/\n/g,\'<br>\');'));
		$form -> addElement('submit','submit','Submit');
		$form -> addElement('button','cancel','Cancel',$this->create_back_href());
		$theme->assign_form('form', $form);
		$theme->assign('board_name','<a '.$this->create_unique_href(array('action'=>'view_board','board'=>$board)).'>'.DB::GetOne('SELECT name FROM apps_forum_board WHERE id = %d',$board).'</a>');
		$theme->assign('forum_boards','<a '.$this->create_unique_href(array('action'=>'__NONE__')).'>'.$this->lang->t('Forum Boards').'</a>');
		if ($form->validate() && Base_AclCommon::i_am_user()){
			DB::Execute('INSERT INTO apps_forum_thread (topic, apps_forum_board_id) VALUES (%s,%d)',array($form->exportValue('topic'),$board));
			$id = DB::GetOne('SELECT id FROM apps_forum_thread WHERE topic=%s AND apps_forum_board_id=%d',array($form->exportValue('topic'),$board));
			$comment = & $this->init_module('Utils/Comment','apps_forum_'.$this->key.'_'.$id);
			$comment->add_post($form->exportValue('post_content'));
			$this->set_module_variable('action','view_board');
			location(array());
		} else
			$theme -> display('New_Thread');
	}

	public function view_thread(){
		$thread = $this->get_module_variable('thread');
		$board = $this->get_module_variable('board');
		$board_name = DB::GetOne('SELECT name FROM apps_forum_board WHERE id = %d',$board);
		if ($_REQUEST['thread']) $thread = $_REQUEST['thread'];
		$this->set_module_variable('thread',$thread);
		$comment = & $this->init_module('Utils/Comment','apps_forum_'.$this->key.'_'.$thread);
		
		$comment->set_moderator(Base_AclCommon::i_am_admin());
		$comment->set_per_page(20);
		$comment->reply_on_comment_page(false);
		$comment->tree_structure(false);

		Base_ActionBarCommon::add_icon('back',$board_name,$this->create_unique_href(array('action'=>'view_board','board'=>$board)));

		ob_start();
		$this -> display_module($comment);	
		$posts = ob_get_contents();
		ob_end_clean();

		$theme = & $this->init_module('Base/Theme');

		$theme -> assign('posts',$posts);
		$theme -> assign('topic',DB::GetOne('SELECT topic FROM apps_forum_thread WHERE id = %d',$thread));
		$theme -> assign('forum_boards','<a '.$this->create_unique_href(array('action'=>'__NONE__')).'>'.$this->lang->t('Forum Boards').'</a>');
		$theme -> assign('board_name','<a '.$this->create_unique_href(array('action'=>'view_board','board'=>$board)).'>'.$board_name.'</a>');
		$theme -> display('View_Thread');
	}
}

?>