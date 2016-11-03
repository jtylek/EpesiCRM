jump_to_record_id = function (tab) {
	if (!jq("#jump_to_record_input").is(":hidden"))
		jq("#jump_to_record_input").hide();
	else
		jq("#jump_to_record_input").show();
	focus_by_id("jump_to_record_input");
}
