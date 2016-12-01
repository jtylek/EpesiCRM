<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

Utils_CommonDataCommon::new_array('System',array(),false,true);
Utils_CommonDataCommon::new_array('System/export_params'  ,array(
             'charset'              => 'UTF-8' //Charset in html/php style (for Polish CP1250)
            ,'field_separator'      => ','      //field separator. 
            ,'decimal_separator'    => '.'      //decimal separator for float or currency
            ,'end_line_type'        => 'UNIX'   //valid values are: WIN, WINDOWS, LIN, LINUX, UNI, UNIX, MAC, MACINTISH
            ,'text_space_indicator' => 1        // add leading and ending separators to string witch spaces inside or not to add
            ,'text_space_separator' => '"'      //leading and ending separator char for strings with spaces if 'text_space_indicator' = 1
            ), true,false
        );

//dla Polaków ze starymi excelami polecam export zdefiniowany poniżej:
/*
Utils_CommonDataCommon::new_array('System/export_params'  ,array(
             'charset'              => 'CP1250' //Charset in html/php style (for Polish CP1250)
            ,'field_separator'      => ';'      //field separator. 
            ,'decimal_separator'    => ','      //decimal separator for float or currency
            ,'end_line_type'        => 'WIN'   //valid values are: WIN, WINDOWS, LIN, LINUX, UNI, UNIX, MAC, MACINTISH
            ,'text_space_indicator' => 0        // add leading and ending separators to string witch spaces inside or not to add
            ,'text_space_separator' => '"'      //leading and ending separator char for strings with spaces if 'text_space_indicator' = 1
            ), true,false
        );
*/
?>

