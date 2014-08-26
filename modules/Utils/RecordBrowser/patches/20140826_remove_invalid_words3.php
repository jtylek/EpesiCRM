<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

Patch::set_message('Processing words');
$words_checkpoint = Patch::checkpoint('words');
if(!$words_checkpoint->is_done()) {
    if($words_checkpoint->has('words')) {
        $words = $words_checkpoint->get('words');
    } else {
        $words = 0;
    }
    if($words_checkpoint->has('words_qty')) {
        $words_qty = $words_checkpoint->get('words_qty');
    } else {
        $words_qty = DB::GetOne('SELECT MAX(id) FROM recordbrowser_words_index');
        $words_checkpoint->set('words_qty',$words_qty);
    }
    
    for(;$words<=$words_qty;$words++) {
        Patch::set_message('Processing word: '.$id.'/'.$words_qty);
        $id = $words;
        $word = DB::GetOne('SELECT word FROM recordbrowser_words_index WHERE id=%d',array($id));

        $words_checkpoint->require_time(1);

        if(preg_match('/[^\p{L}0-9]/u',$word)) {
            DB::Execute('DELETE FROM recordbrowser_words_map WHERE word_id=%d',array($id));
            DB::Execute('DELETE FROM recordbrowser_words_index WHERE id=%d',array($id));
        }

        $words_checkpoint->set('words',$words);
    }
    
    $words_checkpoint->done();
}
