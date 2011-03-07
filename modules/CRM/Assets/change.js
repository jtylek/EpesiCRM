/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */


function change(index) {
    dispComp = '';
    dispMon = '';
    dispPrint = '';

    if(index != 5) {
        if(index > 2) dispComp = 'none';
        if(index != 3) dispMon = 'none';
        if(index != 4) dispPrint = 'none';
    }
    $('_host_name__data').parentNode.style.display=dispComp;
    $('_operating_system__data').parentNode.style.display=dispComp;
    $('_processor__data').parentNode.style.display=dispComp;
    $('_ram__data').parentNode.style.display=dispComp;
    $('_hdd__data').parentNode.style.display=dispComp;
    $('_optical_devices__data').parentNode.style.display=dispComp;
    $('_audio__data').parentNode.style.display=dispComp;
    $('_software__data').parentNode.style.display=dispComp;

    $('_display_type__data').parentNode.style.display=dispMon;
    $('_screen_size__data').parentNode.style.display=dispMon;

    if(index == 2)
        $('_screen_size__data').parentNode.style.display='';

    $('_printer_type__data').parentNode.style.display=dispPrint;
    $('_color_printing__data').parentNode.style.display=dispPrint;
}