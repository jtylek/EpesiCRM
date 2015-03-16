<?php
/**
 * @author    Adam Bukowski <abukowski@telaxus.com>
 * @version   1.0
 * @copyright Copyright &copy; 2015, Telaxus LLC
 * @license   MIT
 * @package   epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class FileCache
{
    private $file;
    private $data = array();

    function __construct($file)
    {
        $this->file = $file;
        $this->init();
    }

    protected function init()
    {
        $data = false;
        if (file_exists($this->file)) {
            global $global_cache;
            @include $this->file;
            $data = isset($global_cache) ? $global_cache : false;
            if (!$data) {
                @unlink($this->file);
            }
        }
        $this->data = is_array($data) ? $data : array();
    }

    protected function save()
    {
        $data_str = var_export($this->data, true);
        $data = '<?php $global_cache = ' . $data_str . ';';
        file_put_contents($this->file, $data);
    }

    public function get($name, $default = null)
    {
        $ret = isset($this->data[$name]) ? $this->data[$name] : $default;
        return $ret;
    }

    public function set($name, $value)
    {
        $this->data[$name] = $value;
        $this->save();
    }

    public function clear($name = null)
    {
        if ($name === null) {
            $this->data = array();
        } else {
            unset($this->data[$name]);
        }
        $this->save();
    }

}

class Cache
{
    protected static $cache_object;

    public static function init($cache_object)
    {
        self::$cache_object = $cache_object;
    }

    public static function get($name, $default = null)
    {
        return self::$cache_object->get($name, $default);
    }

    public static function set($name, $value)
    {
        return self::$cache_object->set($name, $value);
    }

    public static function clear($name = null)
    {
        return self::$cache_object->clear($name);
    }

}

Cache::init(new FileCache(DATA_DIR . '/cache/common_cache.php'));
