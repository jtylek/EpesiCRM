jump_to_record_id = function (tab) {
	if (document.getElementById("jump_to_record_input").style.display=="")
		document.getElementById("jump_to_record_input").style.display = "none";
	else
		document.getElementById("jump_to_record_input").style.display = "";
	focus_by_id("jump_to_record_input");
}
