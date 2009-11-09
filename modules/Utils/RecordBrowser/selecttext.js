function fnSelect(obj) {
    fnDeSelect();
    if (document.selection) {
        var range = document.body.createTextRange();
        range.moveToElementText(obj);
        range.select();
    }
    else if (window.getSelection) {
        var range = document.createRange();
        range.selectNode(obj);
        window.getSelection().addRange(range);
    }
}

function fnDeSelect() {
    if (document.selection) document.selection.empty();
    else if (window.getSelection)
        window.getSelection().removeAllRanges();
}
