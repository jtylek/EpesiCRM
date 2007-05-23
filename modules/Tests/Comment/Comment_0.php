<?php

class Tests_Comment extends Module{
	public function body(){
		print('This is an example comment page.');
		$com = & $this->init_module('Utils/Comment');
		$com -> set_moderator(true);
		$com -> set_per_page(3);
		$com -> reply_on_comment_page(false);
		$com -> tree_structure(true);
		$this -> display_module($com,'mkey');
		//------------------------------ print out src
		print('<hr><b>Install</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Comment/CommentInstall.php');
		print('<hr><b>Init</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Comment/CommentInit_0.php');
		print('<hr><b>Main</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Comment/Comment_0.php');
		print('<hr><b>Common</b><br>');
		$this->pack_module('Utils/CatFile','modules/Tests/Comment/CommentCommon_0.php');
		
	}
}

?>
