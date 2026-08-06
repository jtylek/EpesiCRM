<?php
// Minimal Minify stub to satisfy require()/usage in legacy code.
// If your project uses a different API, extend these signatures accordingly.

class Minify
{
    /** @param string|resource $content */
    public static function minify($content): string
    {
        return (string) $content;
    }
}
