var Apps_MailClient = {
preview: function(id,query,subject,from) {
    var iframe = $(id);
    if(!iframe) return;
    iframe.src = 'modules/Apps/MailClient/preview.php?'+query;
    var subj = $(id+'_subject');
    if(subj)
        subj.innerHTML = subject;
    var fr = $(id+'_from');
    if(fr)
        fr.innerHTML = from;

}
}