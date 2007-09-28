getelem = function(form,elem) {
f=$(form);
if(f){
e=f.elements[elem];
}
return e;
};

settextvalue = function(form,elem,value) {
e=getelem(form,elem);
if(e){
e.value=value;
}};

setselectvalue = function(form,elem,value) {
e=getelem(form,elem);
if(e){
for(i=0; i<e.length; i++)if(e.options[i].value==value){e.options[i].selected=true;break;};
}};

setcheckvalue = function(form,elem,value) {
e=getelem(form,elem);
if(e){
e.checked=value;
}};

setradiovalue =  function(form,elem,value) {
e=getelem(form,elem);
if(e){
for(i=0; i<e.length; i++){e[i].checked=false;if(e[i].value==value)e[i].checked=true;};
}};

seterror=function(err_id, error){
t=$(err_id);
if (error!="") t.innerHTML = error+"<br>";
else if (t) t.innerHTML = error;
};
