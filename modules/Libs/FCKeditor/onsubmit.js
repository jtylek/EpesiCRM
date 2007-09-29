fckeditor_onsubmit = function(ff) {
if(typeof(FCKeditorAPI)!='undefined')
for(var name in FCKeditorAPI.__Instances){var oEditor=FCKeditorAPI.__Instances[ name ];if(!ff || (oEditor.GetParentForm && oEditor.GetParentForm()==ff))oEditor.UpdateLinkedField();}
}
