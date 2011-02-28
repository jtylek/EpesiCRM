ckeditor_onsubmit = function(ff) {
if(typeof(CKeditorAPI)!='undefined')
for(var name in CKeditorAPI.__Instances){var oEditor=CKeditorAPI.__Instances[ name ];if(!ff || (oEditor.GetParentForm && oEditor.GetParentForm()==ff))oEditor.UpdateLinkedField();}
}
