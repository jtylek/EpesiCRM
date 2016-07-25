<?php
/**
 * Use this module if you want to add attachments to some page.
 * Owner of note has always 3x(private,protected,public) write&read.
 * Permission for group is set by methods allow_{private,protected,public}.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage attachment
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Attachment extends Module {
	private $group;

	private $caption = '';
	
	private $watchdog_category;
	private $watchdog_id;
	
	private $func = null;
	private $args = array();

    private $force_multiple = false;


	public function construct($group=null,$watchdog_cat=null,$watchdog_id=null,$func=null,$args=null) {
		$this->group = & $this->get_module_variable('group',isset($group)?$group:null);
		$this->func = & $this->get_module_variable('func',isset($func)?$func:null);
		$this->args = & $this->get_module_variable('args',isset($args)?$args:null);

		if(isset($watchdog_cat)) $this->watchdog_category = $watchdog_cat;
		if(isset($watchdog_id)) $this->watchdog_id = $watchdog_id;
	}
	
	public function set_view_func($x, array $y=array()) {
		$this->func = $x;
		$this->args = $y;
	}

    public function set_multiple_group_mode($arg = true)
    {
        $this->force_multiple = $arg;
    }

	public function body($arg=null, $rb=null, $uid=null) {
		if(isset($arg) && isset($rb)) {
			$this->group = $rb->tab.'/'.$arg['id'];
			if(Utils_WatchdogCommon::get_category_id($rb->tab)!==null) {
				$this->watchdog_category = $rb->tab;
				$this->watchdog_id = $arg['id'];
			}
			$this->set_view_func(array('Utils_RecordBrowserCommon','create_default_linked_label'),array($rb->tab, $arg['id']));
		}
		if (!isset($this->group) && !$uid) trigger_error('Key not given to attachment module',E_USER_ERROR);
		
		$_SESSION['client']['utils_attachment_group'] = $this->group;

        load_js('modules/Utils/Attachment/attachments.js');
        Base_ThemeCommon::load_css('Utils_Attachment','browse');

        $this->rb = $this->init_module(Utils_RecordBrowser::module_name(),'utils_attachment','utils_attachment');
        $defaults = array(
            'permission' => Base_User_SettingsCommon::get('CRM_Common','default_record_permission'),
            'func' => serialize($this->func),
            'args' => serialize($this->args));
        $rb_cols = array();
        $single_group = (is_string($this->group) || count($this->group) == 1);
        if ($this->force_multiple) {
            $single_group = false;
        }
        if ($single_group) {
            $group = is_string($this->group) ? $this->group : reset($this->group);
            $defaults['local'] = $group;
        } else {
            // force attached to display
            $rb_cols['attached_to'] = true;
            $this->rb->set_button(false);
        }
        $this->rb->set_defaults($defaults);
        $this->rb->set_additional_actions_method(array($this,'add_actions'));
        $this->rb->set_header_properties(array(
            'sticky'=>array('width'=>1,'display'=>false),
            'attached_to' => array('width'=>"16em"),
            'edited_on'=>array('width'=>"12em"),
            'title'=>array('width'=>"20em"),
        ));

        if($uid) {
            $this->rb->set_button(false);
            $this->rb->disable_actions(array('delete'));
            $this->display_module($this->rb, array(array(':Created_by'=>$uid), $rb_cols, array('sticky'=>'DESC', 'edited_on'=>'DESC')), 'show_data');
        } else {
            $crits = array();
            if(!is_array($this->group)) $this->group = array($this->group);

            if(isset($_SESSION['attachment_copy']) && count($this->group)==1 && $_SESSION['attachment_copy']['group']!=$this->group) {
                $this->rb->new_button(Base_ThemeCommon::get_template_file(Utils_Attachment::module_name(), 'link.png'),__('Paste'),
                    Utils_TooltipCommon::open_tag_attrs($_SESSION['attachment_copy']['text']).' '.$this->create_callback_href(array($this,'paste'))
                );
            }

            if($this->group) {
                $g = array_map(array('DB','qstr'),$this->group);
                $crits['id'] = DB::GetCol('SELECT attachment FROM utils_attachment_local WHERE local IN ('.implode(',',$g).')');
            } else $crits['id'] = 0;
            $this->display_module($this->rb, array($crits, $rb_cols, array('sticky'=>'DESC', 'edited_on'=>'DESC')), 'show_data');
        }
	}

    public function add_actions($row,$r,$rb) {
        if(($row['crypted'] && !isset($_SESSION['client']['cp'.$row['id']])) || count($this->group)!=1) return;
        $text = $row['note'];
        if($row['crypted'])
            $text = Utils_AttachmentCommon::decrypt($text,$_SESSION['client']['cp'.$row['id']]);
        $r->add_action($this->create_callback_href(array($this,'copy'),array($row['id'],$text, $this->group)),__('Copy link'),null,Base_ThemeCommon::get_template_file($this->get_type(),'copy_small.png'), 3);
        $r->add_action($this->create_confirm_callback_href(__('Are you sure you want to cut this note?'), array($this, 'cut'), array($row['id'], $text, $this->group)), __('Cut'), null, Base_ThemeCommon::get_template_file($this->get_type(), 'cut_small.png'), 4);
    }

	public function copy($id, $text, $group) {
	 	$_SESSION['attachment_copy'] = array('id'=>$id, 'group'=>$group,'text'=>$text);
        $_SESSION['attachment_cut'] = 0;
	}

	public function cut($id, $text, $group) {
	 	$_SESSION['attachment_copy'] = array('id'=>$id, 'group'=>$group,'text'=>$text);
	 	$_SESSION['attachment_cut'] = 1;
	}

	public function paste() {
        $group = reset($this->group);

        if(DB::GetOne('SELECT 1 FROM utils_attachment_local WHERE attachment=%d AND local=%s',array($_SESSION['attachment_copy']['id'],$group))) return;
        if (isset($_SESSION['attachment_cut']) && $_SESSION['attachment_cut']) {
            $source_group = reset($_SESSION['attachment_copy']['group']);
            DB::Execute('UPDATE utils_attachment_local SET local=%s,func=%s,args=%s WHERE attachment=%d AND local=%s', array($group, serialize($this->func), serialize($this->args), $_SESSION['attachment_copy']['id'], $source_group));
            Utils_AttachmentCommon::new_watchdog_event($group, '+', $_SESSION['attachment_copy']['id']);
            Utils_AttachmentCommon::new_watchdog_event($source_group, '-', $_SESSION['attachment_copy']['id']);
            unset($_SESSION['attachment_cut']);
            unset($_SESSION['attachment_copy']);
		} else {
            DB::Execute('INSERT INTO utils_attachment_local(local,attachment,func,args) VALUES(%s,%d,%s,%s)',array($group,$_SESSION['attachment_copy']['id'],serialize($this->func),serialize($this->args)));
            Utils_AttachmentCommon::new_watchdog_event($group, '+', $_SESSION['attachment_copy']['id']);
		}
	}

	public function file_history($attachment) {
        if($this->is_back()) {
            $x = ModuleManager::get_instance('/Base_Box|0');
            if(!$x) trigger_error('There is no base box module instance',E_USER_ERROR);
            return $x->pop_main();
        }

        Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());

        $file_leightbox_href = array();
        $id = $attachment['id'];

        $tb = $this->init_module(Utils_TabbedBrowser::module_name());
        $tb->start_tab('File history');
        $gb = $this->init_module(Utils_GenericBrowser::module_name(),null,'hua'.$id);
        $gb->set_inline_display();
        $gb->set_table_columns(array(
            array('name'=>__('Deleted'), 'order'=>'deleted','width'=>10),
            array('name'=>__('Date'), 'order'=>'upload_on','width'=>25),
            array('name'=>__('Who'), 'order'=>'upload_by','width'=>25),
            array('name'=>__('File'), 'order'=>'uaf.original')
        ));
        $gb->set_default_order(array(__('Date')=>'DESC'));

        $ret = $gb->query_order_limit('SELECT uaf.id,uaf.deleted,uaf.filestorage_id,uaf.created_on as upload_on,uaf.created_by as upload_by,uaf.original FROM utils_attachment_file uaf WHERE uaf.attach_id='.$id, 'SELECT count(*) FROM utils_attachment_file uaf WHERE uaf.attach_id='.$id);
        while($row = $ret->FetchRow()) {
            $r = $gb->get_new_row();
            if ($row['deleted']) $r->add_action($this->create_confirm_callback_href(__('Are you sure you want to restore attached file?'),array($this,'restore_file'),array($row['id'])),'restore',__('Restore'));
            $view_link = '';
            $lb = array();
            $lb['aid'] = $id;
            $lb['crypted'] = $attachment['crypted'];
            $lb['original'] = $row['original'];
            $lb['id'] = $row['id'];
            $lb['filestorage_id'] = $row['filestorage_id'];
            $file_leightbox_href[$row['id']] = Utils_AttachmentCommon::get_file_leightbox($lb,$view_link);
            $file = '<a '.$file_leightbox_href[$row['id']].'>'.$row['original'].'</a>';
            $r->add_data($row['deleted']?__('Yes'):__('No'),Base_RegionalSettingsCommon::time2reg($row['upload_on']),Base_UserCommon::get_user_label($row['upload_by']),$file);
        }
        $this->display_module($gb);
        $tb->end_tab();
        $tb->start_tab('File access history');
        $gb = $this->init_module(Utils_GenericBrowser::module_name(),null,'hda'.$id);
        $gb->set_inline_display();
        $gb->set_table_columns(array(
        	array('name'=>__('File'), 'order'=>'original','width'=>15),
            array('name'=>__('Create date'), 'order'=>'created_on','width'=>15),
            array('name'=>__('Download date'), 'order'=>'download_on','width'=>15),
            array('name'=>__('Who'), 'order'=>'created_by','width'=>15),
            array('name'=>__('IP Address'), 'order'=>'ip_address', 'width'=>15),
            array('name'=>__('Host Name'), 'order'=>'host_name', 'width'=>15),
            array('name'=>__('Method description'), 'order'=>'description', 'width'=>20),
            array('name'=>__('Remote'), 'order'=>'remote', 'width'=>10),
        ));
        $gb->set_default_order(array(__('Create date')=>'DESC'));

        $query = 'SELECT uaf.id,uaf.original,uad.created_on,uad.download_on,(SELECT l.login FROM user_login l WHERE uad.created_by=l.id) as created_by,uad.remote,uad.ip_address,uad.host_name,uad.description FROM utils_attachment_download uad INNER JOIN utils_attachment_file uaf ON uaf.id=uad.attach_file_id WHERE uaf.attach_id='.$id;
        $query_qty = 'SELECT count(*) FROM utils_attachment_download uad INNER JOIN utils_attachment_file uaf ON uaf.id=uad.attach_file_id WHERE uaf.attach_id='.$id;
        if(Base_AclCommon::check_permission('Attachments - view full download history'))
            $ret = $gb->query_order_limit($query, $query_qty);
        else {
            print('You are allowed to see your own downloads only');
            $who = ' AND uad.created_by='.Acl::get_user();
            $ret = $gb->query_order_limit($query.$who, $query_qty.$who);
        }
        while($row = $ret->FetchRow()) {
            $r = $gb->get_new_row();
            if (isset($file_leightbox_href[$row['id']]))
            	$file = '<a '.$file_leightbox_href[$row['id']].'>'.$row['original'].'</a>';
            else 
            	$file = $row['original'];
            $r->add_data($file,Base_RegionalSettingsCommon::time2reg($row['created_on']),($row['remote']!=1?Base_RegionalSettingsCommon::time2reg($row['download_on']):''),$row['created_by'], $row['ip_address'], $row['host_name'], $row['description'], ($row['remote']==0?'no':'yes'));
        }
        $this->display_module($gb);
        $tb->end_tab();
        $this->display_module($tb);

        $this->caption = 'Note history';

        return true;
    }

	public function restore_file($id) {
		DB::Execute('UPDATE utils_attachment_file SET deleted=0 WHERE id=%d',array($id));
		return false;
	}

    public function caption() {
		return $this->caption;
	}

    public function enable_watchdog($category, $id) {
		$this->watchdog_category = $category;
		$this->watchdog_id = $id;
	}

	public function user_addon($uid) {
		$this->body(null, null, $uid);
	}
}

?>
