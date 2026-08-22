<?php

/**
 * Custom Configuration file for TCPDF.
 * 
 * @author Georgi Hristov <ghristov@gmx.de>
 * @license MIT
 * @package epesi-libs
 * @subpackage tcpdf
 */

/**
 * cache directory for temporary files (full path)
 */
define('K_PATH_CACHE', DATA_DIR . '/Libs_TCPDF/');

/**
 * cache directory for temporary files (url path)
 */
define('K_PATH_URL_CACHE', DATA_DIR . '/Libs_TCPDF/');

/**
 * images directory
 */
define('K_PATH_IMAGES', '');

/**
 * blank image
 */
define('K_BLANK_IMAGE', K_PATH_IMAGES . '_blank.png');

/**
 * page format
 */
define('PDF_PAGE_FORMAT', 'A4');

/**
 * page orientation (P=portrait, L=landscape)
 */
define('PDF_PAGE_ORIENTATION', 'P');

/**
 * document creator
 */
define('PDF_CREATOR', EPESI);

/**
 * document author
 */
define('PDF_AUTHOR', EPESI);

/**
 * header title
 */
define('PDF_HEADER_TITLE', EPESI . ' Example');

/**
 * header description string
 */
define('PDF_HEADER_STRING', EPESI);

/**
 * image logo
 */
define('PDF_HEADER_LOGO', 'tcpdf_logo.jpg');

/**
 * header logo image width [mm]
 */
define('PDF_HEADER_LOGO_WIDTH', 30);

/**
 * document unit of measure [pt=point, mm=millimeter, cm=centimeter, in=inch]
 */
define('PDF_UNIT', 'mm');

/**
 * header margin
 */
define('PDF_MARGIN_HEADER', 5);

/**
 * footer margin
 */
define('PDF_MARGIN_FOOTER', 10);

/**
 * top margin
 */
define('PDF_MARGIN_TOP', 22);

/**
 * bottom margin
 */
define('PDF_MARGIN_BOTTOM', 25);

/**
 * left margin
 */
define('PDF_MARGIN_LEFT', 15);

/**
 * right margin
 */
define('PDF_MARGIN_RIGHT', 15);

/**
 * default main font name
 */
define('PDF_FONT_NAME_MAIN', 'helvetica');

/**
 * default main font size
 */
define('PDF_FONT_SIZE_MAIN', 10);

/**
 * default data font name
 */
define('PDF_FONT_NAME_DATA', 'helvetica');

/**
 * default data font size
 */
define('PDF_FONT_SIZE_DATA', 8);

/**
 * default monospaced font name
 */
define('PDF_FONT_MONOSPACED', 'courier');

/**
 * ratio used to adjust the conversion of pixels to user units
 */
define('PDF_IMAGE_SCALE_RATIO', 1.25);

/**
 * magnification factor for titles
 */
define('HEAD_MAGNIFICATION', 1.1);

/**
 * height of cell repect font height
 */
define('K_CELL_HEIGHT_RATIO', 1.25);

/**
 * title magnification respect main font size
 */
define('K_TITLE_MAGNIFICATION', 1.3);

/**
 * reduction factor for small font
 */
define('K_SMALL_RATIO', 2 / 3);

/**
 * set to true to enable the special procedure used to avoid the overlappind of symbols on Thai language
 */
define('K_THAI_TOPCHARS', true);

/**
 * if true allows to call TCPDF methods using HTML syntax
 * IMPORTANT: For security reason, disable this feature if you are printing user HTML content.
 */
define('K_TCPDF_CALLS_IN_HTML', true);
