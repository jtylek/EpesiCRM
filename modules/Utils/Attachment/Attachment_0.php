<?php
/**
 * Use this module if you want to add attachments to some page.
 * Owner of note has always 3x(private,protected,public) write&read.
 * Permission for group is set by methods allow_{private,protected,public}.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
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
		$this->group = & $this->get_module_variable('group',$group ?? null);
		$this->func = & $this->get_module_variable('func',$func ?? null);
		$this->args = & $this->get_module_variable('args',$args ?? null);

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

		load_js($this->get_module_dir() . 'attachments.js');
        Base_ThemeCommon::load_css('Utils_Attachment','browse');

        $this->rb = $this->init_module(Utils_RecordBrowser::module_name(),'utils_attachment','utils_attachment');
        // Opt this addon's grid out of the generic 767.98px 2-line-per-row mobile layout
        // (theme_adminltedark/default.css's "mobile: 2-line-per-row layout" block) - its
        // 'note' column is a wide tall_preview text column, not a fixed value that benefits
        // from an even grid split, and it rendered fine as a plain proportional-width table
        // before that fix. Same set_prefix()/set_postfix() wrapping technique as this file's
        // own view_edit_history()/".epesi-rb-changes-history" opt-out, just injected via
        // set_data_gb() since this addon calls show_data() directly rather than body().
        $gb = $this->rb->init_module(Utils_GenericBrowser::module_name(), null, 'utils_attachment');
        $gb->set_prefix('<div class="epesi-attachment-notes">');
        $gb->set_postfix('</div>');
        $this->rb->set_data_gb($gb);
        $defaults = array(
            'permission' => Base_User_SettingsCommon::get('CRM_Common','default_record_permission'),
            'func' => serialize($this->func),
            'args' => serialize($this->args));
        $rb_cols = array();
        $single_group = (is_string($this->group) || count($this->group) == 1) && !$this->force_multiple;
        if ($single_group) {
            $group = is_string($this->group) ? $this->group : reset($this->group);
            $defaults['attached_to'] = array($group);
        } else {
            // force attached to display
            $rb_cols['attached_to'] = true;
            $this->rb->set_button(false);
        }
        $this->rb->set_defaults($defaults);
        $this->rb->set_additional_actions_method($this->add_actions(...));
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
            if(!is_array($this->group)) $this->group = array($this->group);

            if(isset($_SESSION['attachment_copy']) && count($this->group)==1 && $_SESSION['attachment_copy']['group']!=$this->group) {
                $this->rb->new_button(Base_ThemeCommon::get_template_file(Utils_Attachment::module_name(), 'link.png'),__('Paste'),
                    Utils_TooltipCommon::open_tag_attrs($_SESSION['attachment_copy']['text']).' '.$this->create_callback_href($this->paste(...))
                );
            }
			$crits = array(
					'attached_to' => $this->group ?: 0
			);

           	$this->display_module($this->rb, array($crits, $rb_cols, array('sticky'=>'DESC', 'edited_on'=>'DESC')), 'show_data');
        }
	}

    public function add_actions($row,$r,$rb) {
        if(($row['crypted'] && !isset($_SESSION['client']['cp'.$row['id']])) || count($this->group)!=1) return;
        $text = $row['note'];
        if($row['crypted'])
            $text = Utils_AttachmentCommon::decrypt($text,$_SESSION['client']['cp'.$row['id']]);
        $r->add_action($this->create_callback_href($this->copy(...),array($row['id'],$text, $this->group)),__('Copy link'),null,Base_ThemeCommon::get_template_file($this->get_type(),'copy_small.png'), 3);
        $r->add_action($this->create_confirm_callback_href(__('Are you sure you want to cut this note?'), $this->cut(...), array($row['id'], $text, $this->group)), __('Cut'), null, Base_ThemeCommon::get_template_file($this->get_type(), 'cut_small.png'), 4);
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

        if (isset($_SESSION['attachment_cut']) && $_SESSION['attachment_cut']) {
            $source_group = reset($_SESSION['attachment_copy']['group']);
            Utils_AttachmentCommon::move_notes($group, $source_group, [$_SESSION['attachment_copy']['id']]);
            unset($_SESSION['attachment_cut']);
            unset($_SESSION['attachment_copy']);
		} else {
			Utils_AttachmentCommon::attach_note($_SESSION['attachment_copy']['id'], $group);
		}
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

	// 'Features Configuration' admin panel - see AttachmentCommon_0.php's
	// admin_caption()/processing_related(). Same shape as CRM_Tasks/
	// CRM_Meeting's own admin(): just a plain RecordBrowser CRUD screen over
	// the module's own '_related' table.
	public function admin() {
		if ($this->is_back()) {
			$this->parent->reset();
			return;
		}
		$rb = $this->init_module(Utils_RecordBrowser::module_name(), 'utils_attachment_related', 'utils_attachment_related');
		$this->display_module($rb);
		Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
	}
}

?>
