<?php
/**
 * removes any xss from html passed as string
 *
 *
 * @author Janusz Tylek <j@epe.si>
 * @copyright Janusz Tylek
 * @license MIT
 * @version 0.1
 */

class Utils_SafeHtml_SafeHtml
{
    private static $safeHtmler;

    /*
     * cleans string from possible xss
     *
     * @param string    $html raw html
     *
     * @return string   safe string with html without xss
     *
     * @throws Utils_RecordBrowser_SafeHtml_SafeHtmlNotAString
     */
    public static function outputSafeHtml($html)
    {
        try {
            $output = self::$safeHtmler->output($html);
            if(gettype($output) != 'string') {
                throw new Utils_SafeHtml_SafeHtmlNotAString('not a string! Check your SafeHtml class');
            }
        } catch(Utils_SafeHtml_SafeHtmlNotAString $exception) {
            $output = $exception->getMessage();
        }
        return $output;
    }

    /*
     * sets the safe html class
     *
     * @param Utils_RecordBrowser_SafeHtml_SafeHtmlInterface    class used for removing xss from the html
     *
     */
    public static function setSafeHtml(Utils_SafeHtml_SafeHtmlInterface $safeHtmler)
    {
        self::$safeHtmler = $safeHtmler;
    }
}