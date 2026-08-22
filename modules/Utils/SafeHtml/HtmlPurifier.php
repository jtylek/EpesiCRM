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
        // allow data: URIs so pasted clipboard images (base64 <img>) survive purification
        $config->set('URI.AllowedSchemes', array(
            'http' => true, 'https' => true, 'mailto' => true, 'ftp' => true,
            'nntp' => true, 'news' => true, 'tel' => true, 'data' => true,
        ));
        $purifier = new HTMLPurifier($config);
        return $purifier->purify($html);
    }
}