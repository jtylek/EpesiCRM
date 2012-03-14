jump_to_record_id = function (tab) {
	if ($("jump_to_record_input").style.display=="")
		$("jump_to_record_input").style.display = "none";
	else
		$("jump_to_record_input").style.display = "";
	focus_by_id("jump_to_record_input");
}
