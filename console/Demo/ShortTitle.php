<?php

namespace Epesi\Console\Demo;

/**
 * Length-capped titles/subjects for the demo:generate:* commands.
 *
 * Faker's sentence(4) routinely runs past 40 characters ("Voluptatem nihil aut
 * inventore et suscipit"), which makes demo rows wrap in the grid and look
 * nothing like the short subjects people actually type.
 *
 * Static property rather than a constant deliberately: constants in traits need
 * PHP 8.2, and this app supports 8.1+ (README.md, compatibility_check.php).
 */
trait ShortTitle
{
    /** Hard cap, in characters. */
    protected static $title_max = 30;

    /**
     * A short demo title, never longer than $title_max characters.
     *
     * Trimmed on word boundaries so it reads like a real subject line rather
     * than a string cut mid-word.
     *
     * @param \Faker\Generator $faker
     * @return string
     */
    protected function short_title($faker)
    {
        $title = rtrim($faker->sentence(4), '.');
        if (mb_strlen($title) <= self::$title_max) {
            return $title;
        }

        $out = '';
        foreach (explode(' ', $title) as $word) {
            $next = $out === '' ? $word : $out . ' ' . $word;
            if (mb_strlen($next) > self::$title_max) break;
            $out = $next;
        }

        // A first word longer than the cap on its own still has to be cut.
        return $out !== '' ? $out : mb_substr($title, 0, self::$title_max);
    }
}
