<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Janusz Tylek
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage setup
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_SetupCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-image'; }

	public static function body_access() {
		return self::admin_access();
	}

	public static function admin_access() {
	    if(DEMO_MODE) return false;
		// Gated: a stale anonymous_setup no longer opens this screen once a real
		// super-admin exists - see Base_AclCommon::anonymous_setup_active().
		if (Base_AclCommon::anonymous_setup_active()) return true;
		return Base_AclCommon::i_am_admin();
	}

	public static function admin_access_levels() {
		return false;
	}

	public static function admin_caption() {
		if (ModuleManager::is_installed('Base_EpesiStore')>=0
                && Base_EpesiStoreCommon::admin_access()) return null;
		return array('label'=>__('Modules Administration'), 'section'=>__('Server Configuration'));
	}
    
    public static function is_simple_setup() {
        return Variable::get('simple_setup');
    }
    
    public static function set_simple_setup($value) {
        Variable::set('simple_setup', $value);
    }
	
	public static function refresh_available_modules() {
		$module_dirs = ModuleManager::list_modules();
		DB::Execute('TRUNCATE TABLE available_modules');
		foreach($module_dirs as $name => $v)
			foreach($v as $ver => $u) 
				DB::Execute('INSERT INTO available_modules VALUES(%s, %d, %s)',array($name,$ver,$u));
		return $module_dirs;
	}
    
    public static function is_store_visible() {
        $ret = Variable::get('base_setup_store_enabled', false);
        return ($ret === '') ? true : ($ret ? true : false);
    }
    
    public static function set_store_visibility($enabled) {
        Variable::set('base_setup_store_enabled', $enabled ? true : false);
    }

	// Renders modules/<Path>/README.md as an HTML fragment for embedding
	// directly in a Leightbox popup - Simple Setup's per-card "Readme..."
	// button and Advanced Setup's per-module "i" info icon both call this
	// (Setup_0.php::simple_setup()/advanced_setup()) rather than opening a
	// separate tab; the README's own leading "# Module/Path" heading is left
	// as the fragment's title. Returns null when the module ships no
	// README.md, so callers can fall back (Advanced Setup falls back to the
	// plain info() table; Simple Setup simply shows no Readme button).
	public static function get_readme_html($module) {
		$file = 'modules/'.ModuleManager::get_module_dir_path($module).'/README.md';
		if (!is_file($file))
			return null;
		return self::markdown_to_html(file_get_contents($file));
	}

	// Deliberately minimal, dependency-free Markdown -> HTML: headings, bold/
	// italic, inline code, fenced code blocks, ordered/unordered lists,
	// blockquotes, tables, links, hr, paragraphs. Not CommonMark-complete -
	// just enough for the READMEs this codebase actually writes (see
	// modules/Custom/Tutorial/README.md). No vendored library since this is
	// an internal, admin-only viewer for our own trusted, source-controlled
	// docs, not a general-purpose renderer.
	private static function markdown_to_html($md) {
		$md = str_replace("\r\n", "\n", $md);
		$lines = explode("\n", $md);
		$n = count($lines);
		$html = array();
		$in_list = null;
		$paragraph_buf = array();

		$flush_paragraph = function() use (&$paragraph_buf, &$html) {
			if ($paragraph_buf) {
				$html[] = '<p>'.self::markdown_inline(implode(' ', $paragraph_buf)).'</p>';
				$paragraph_buf = array();
			}
		};
		$close_list = function() use (&$in_list, &$html) {
			if ($in_list) {
				$html[] = "</$in_list>";
				$in_list = null;
			}
		};

		$i = 0;
		while ($i < $n) {
			$line = $lines[$i];

			// fenced code block
			if (preg_match('/^```(\S*)\s*$/', $line, $m)) {
				$flush_paragraph();
				$close_list();
				$lang = $m[1];
				$code_lines = array();
				$i++;
				while ($i < $n && !preg_match('/^```\s*$/', $lines[$i])) {
					$code_lines[] = $lines[$i];
					$i++;
				}
				$i++; // skip closing fence (or EOF if the fence was never closed)
				$code = htmlspecialchars(implode("\n", $code_lines), ENT_QUOTES, 'UTF-8');
				$class = $lang ? ' class="language-'.htmlspecialchars($lang, ENT_QUOTES, 'UTF-8').'"' : '';
				$html[] = "<pre><code$class>$code</code></pre>";
				continue;
			}

			// heading
			if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
				$flush_paragraph();
				$close_list();
				$level = strlen($m[1]);
				$html[] = "<h$level>".self::markdown_inline(trim($m[2]))."</h$level>";
				$i++;
				continue;
			}

			// horizontal rule (checked before list items - a bare "---" line
			// is a divider, not an empty unordered-list item)
			if (preg_match('/^\s*(-{3,}|\*{3,}|_{3,})\s*$/', $line)) {
				$flush_paragraph();
				$close_list();
				$html[] = '<hr>';
				$i++;
				continue;
			}

			// table: a "| ... |" header row immediately followed by a
			// "|---|---|"-style separator row
			if (strpos($line, '|') !== false && $i + 1 < $n
					&& preg_match('/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/', $lines[$i + 1])) {
				$flush_paragraph();
				$close_list();
				$header_cells = self::markdown_table_row($line);
				$i += 2;
				$rows = array();
				while ($i < $n && trim($lines[$i]) !== '' && strpos($lines[$i], '|') !== false) {
					$rows[] = self::markdown_table_row($lines[$i]);
					$i++;
				}
				$t = '<table><thead><tr>';
				foreach ($header_cells as $c)
					$t .= '<th>'.self::markdown_inline($c).'</th>';
				$t .= '</tr></thead><tbody>';
				foreach ($rows as $r) {
					$t .= '<tr>';
					foreach ($r as $c)
						$t .= '<td>'.self::markdown_inline($c).'</td>';
					$t .= '</tr>';
				}
				$t .= '</tbody></table>';
				$html[] = $t;
				continue;
			}

			// blockquote (consumes consecutive ">"-prefixed lines as one block)
			if (preg_match('/^>\s?(.*)$/', $line, $m)) {
				$flush_paragraph();
				$close_list();
				$quote_lines = array($m[1]);
				$i++;
				while ($i < $n && preg_match('/^>\s?(.*)$/', $lines[$i], $m2)) {
					$quote_lines[] = $m2[1];
					$i++;
				}
				$html[] = '<blockquote><p>'.self::markdown_inline(implode(' ', $quote_lines)).'</p></blockquote>';
				continue;
			}

			// unordered list item
			if (preg_match('/^\s*[-*]\s+(.*)$/', $line, $m)) {
				$flush_paragraph();
				if ($in_list !== 'ul') { $close_list(); $html[] = '<ul>'; $in_list = 'ul'; }
				$i++;
				$html[] = '<li>'.self::markdown_inline(self::markdown_list_item($m[1], $lines, $i, $n)).'</li>';
				continue;
			}

			// ordered list item
			if (preg_match('/^\s*\d+\.\s+(.*)$/', $line, $m)) {
				$flush_paragraph();
				if ($in_list !== 'ol') { $close_list(); $html[] = '<ol>'; $in_list = 'ol'; }
				$i++;
				$html[] = '<li>'.self::markdown_inline(self::markdown_list_item($m[1], $lines, $i, $n)).'</li>';
				continue;
			}

			// blank line - paragraph/list separator
			if (trim($line) === '') {
				$flush_paragraph();
				$close_list();
				$i++;
				continue;
			}

			// plain paragraph text
			$close_list();
			$paragraph_buf[] = trim($line);
			$i++;
		}
		$flush_paragraph();
		$close_list();
		return implode("\n", $html);
	}

	// A list item's own text often wraps onto following unmarked lines (this
	// file's own README.md does exactly that) - lazily consume every
	// following non-blank line that isn't itself the start of a new block
	// (another list item, a heading, a fence) as a continuation of the same
	// item, same "lazy continuation" leniency CommonMark itself allows.
	// Advances $i (by reference) past whatever it consumes.
	private static function markdown_list_item($first_line, $lines, & $i, $n) {
		$item_lines = array($first_line);
		while ($i < $n && trim($lines[$i]) !== ''
				&& !preg_match('/^\s*[-*]\s+/', $lines[$i])
				&& !preg_match('/^\s*\d+\.\s+/', $lines[$i])
				&& !preg_match('/^#{1,6}\s+/', $lines[$i])
				&& !preg_match('/^```/', $lines[$i])) {
			$item_lines[] = trim($lines[$i]);
			$i++;
		}
		return implode(' ', $item_lines);
	}

	private static function markdown_table_row($line) {
		$line = trim($line);
		$line = preg_replace('/^\|/', '', $line);
		$line = preg_replace('/\|$/', '', $line);
		return array_map('trim', explode('|', $line));
	}

	// Escapes first, then re-adds a small set of inline HTML tags for
	// Markdown's inline syntax. Code spans and links are pulled out to
	// placeholders *before* bold/italic run, and restored after - otherwise
	// bold/italic regexes match straight across a code span's own
	// backticks/asterisks/underscores instead of stopping at them. Hit for
	// real rendering this module's own README.md: back-to-back code spans
	// like `Tutorial_0.php` and `TutorialCommon_0.php` have an underscore
	// each, and without protection an underscore-italic rule paired them up
	// across both spans instead of leaving each filename alone.
	//
	// Underscore-based emphasis (_text_) is deliberately not supported at
	// all, protected or not: this codebase's own identifiers/filenames are
	// full of underscores (simple_setup(), Tutorial_Priority, ...), and
	// CommonMark's word-boundary rule for distinguishing real emphasis from
	// a literal underscore is more machinery than a good-enough internal
	// docs viewer needs. Use **bold**/*italic* in READMEs meant for this
	// renderer.
	private static function markdown_inline($text) {
		$text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

		$placeholders = array();
		$text = preg_replace_callback('/`([^`]+)`/', function($m) use (& $placeholders) {
			$key = "\x00P".count($placeholders)."\x00";
			$placeholders[$key] = '<code>'.$m[1].'</code>';
			return $key;
		}, $text);
		$text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function($m) use (& $placeholders) {
			$key = "\x00P".count($placeholders)."\x00";
			$placeholders[$key] = '<a href="'.$m[2].'" target="_blank" rel="noopener">'.$m[1].'</a>';
			return $key;
		}, $text);

		$text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text);
		$text = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $text);

		return strtr($text, $placeholders);
	}
}
?>
