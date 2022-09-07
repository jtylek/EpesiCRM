<?php
/**
 * HtmlPurifier Factory
 *
 * @author nnader@telaxus.com
 * @author Norbert Nader <nnader@telaxus.com>
 * @copyright Janusz Tylek
 * @license MIT
 * @version 0.1
 */

class Utils_SafeHtml_HtmlPurifier
    implements Utils_SafeHtml_SafeHtmlInterface
{
    public function output($html)
    {
        $config = HTMLPurifier_Config::createDefault();
        $purifier = new HTMLPurifier($config);
        return $purifier->purify($html);
    }
}