<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license MIT
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

require_once('database.php');

class EpesiSession implements SessionHandlerInterface{
    const MAX_SESSION_ID_LENGTH = 128;
    
    /**
     * @var EpesiSessionStorage
     */
    private static $storage;
    
    private static $storageMap = [
    		'file' => EpesiSessionFileStorage::class,
    		'memcache' => EpesiSessionMemcachedStorage::class,
    		'memcached' => EpesiSessionMemcachedStorage::class,
    		'sql' => EpesiSessionDBStorage::class,
    ];

    public static function truncated_id($session_id = null)
    {
        return substr($session_id?: session_id(), 0, self::MAX_SESSION_ID_LENGTH);
    }
    
    public static function create()
    {
        return new static();
    }
    
    public static function get($name)
    {    	
    	return self::storage()->read($name);
    }
    
    public static function set($name, $data)
    {
    	return self::storage()->write($name, $data);
    }    
    
    public static function destroy_client($name, $i)
    {
    	$name = self::truncated_id($name);
    	
    	self::storage()->clear($name . '_' . $i);
    	
    	DB::Execute('DELETE FROM history WHERE session_name=%s AND client_id=%d',array($name,$i));
    }    

    public function open($path, $name)
    {
    	self::storage();
    	
        return true;
    }
    
    /**
     * @return EpesiSessionStorage
     */
    public static function storage()
    {
    	$lifetime = min(ini_get('session.gc_maxlifetime'),2592000-1); //less then 30 days
    	
    	self::$storage = self::$storage?: EpesiSessionStorage::factory(self::getStorageClass(), $lifetime);
    	
    	return self::$storage;
    }
    
    public static function getStorageClass()
    {
    	$defaultClass = reset(self::$storageMap);
    	
    	$class = self::$storageMap[SESSION_TYPE]?? $defaultClass;
    	
    	return $class::active()? $class: $defaultClass;
    }
    
    public function close() {
        return true;
    }

    public function read($name)
    {
        $name = self::truncated_id($name);
        
        // ----- main session -----
        if ($mainData = self::storage()->read($name))
        	$_SESSION = $mainData;
        
        if (CID===false) return '';
        
        if(!is_numeric(CID))
            trigger_error('Invalid client id.',E_USER_ERROR);

        if(isset($_SESSION['session_destroyed'][CID])) return '';
        
        // ----- client session -----
        if ($clientData = self::storage()->read($name . '_' . CID))
        	$_SESSION['client'] = $clientData;

        $_SESSION['client']['__module_vars__'] = $_SESSION['client']['__module_vars__']?? [];
        
        return '';
    }

    public function write($name, $data)
    {
        if(READ_ONLY_SESSION) return true;
        
        $name = self::truncated_id($name);
        
        if(defined('SESSION_EXPIRED')) {
        	foreach ([
        			$name,
        			$name .'_' . CID
        	] as $key) {
        		self::storage()->unlock($key);
        	}
        	
            return true;
        }
        
        $ret = 1;
        if(CID!==false && isset($_SESSION['client']) && !isset($_SESSION['session_destroyed'][CID])) {
        	$ret &= self::storage()->write($name . '_' . CID, $_SESSION['client']);
        }
        
        unset($_SESSION['client']);
        
        $ret &= self::storage()->write($name, $_SESSION);
        
		$ret &= (bool) DB::Replace('session', [
				'expires' => time(),
				'name' => DB::qstr($name)
		], 'name');

        return $ret > 0;
    }
    
    public function destroy($name)
    {
        $name = self::truncated_id($name);
        $cids = DB::GetCol('SELECT DISTINCT client_id FROM history WHERE session_name=%s',array($name));
        foreach($cids as $i)
            self::destroy_client($name,$i);
        
        self::storage()->clear($name);
        
        DB::BeginTrans();
        DB::Execute('DELETE FROM history WHERE session_name=%s',array($name));
        DB::Execute('DELETE FROM session WHERE name=%s',array($name));
        DB::CommitTrans();
        return true;
    }

    public function gc($lifetime)
    {
        $before = time() - $lifetime;
        $ret = DB::Execute('SELECT name FROM session WHERE expires <= %d', [$before]);
        while($row = $ret->FetchRow()) {
            $this->destroy($row['name']);
        }
        
        self::storage()->cleanup($before);

        return true;
    }
}

abstract class EpesiSessionStorage
{
	protected static $token = '';
	protected $lifetime;
	
	public static function factory($class, $lifetime)
	{
		return new $class($lifetime);
	}
	
	public function __construct($lifetime)
	{
		$this->setLifetime($lifetime);
	}
	
	public static function active()
	{
		return true;
	}
	
	public static function tokenize($key)
	{
		return self::$token . $key;
	}
	
	/**
	 * Read value from storage corresponding to key
	 * 
	 * @param string $key
	 */
	abstract public function read($key);
	
	/**
	 * Write value to storage
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	abstract public function write($key, $value);
	
	abstract public function clear($key);
	
	public function lock($key) {}
	
	public function unlock($key) {}
	
	public function cleanup($lifetime) {}
	
	public function getLifetime()
	{
		return $this->lifetime;
	}

	public function setLifetime($lifetime)
	{
		$this->lifetime = $lifetime;
		
		return $this;
	}
}

class EpesiSessionFileStorage extends EpesiSessionStorage 
{
	protected static $token = FILE_SESSION_TOKEN;
	protected static $filePointers;	
	
	public function read($name)
	{
		$file = self::getFile($name);		
		
		$filePointer = self::getFilePointer($file);
		
		if(!READ_ONLY_SESSION && !flock($filePointer, LOCK_EX))
			trigger_error('Unable to get lock on session file=' . $file, E_USER_ERROR);
		
		$ret = stream_get_contents($filePointer);
			
		return $ret? unserialize($ret): '';
	}
	
	public function write($name, $data)
	{
		$filePointer = self::getFilePointer(self::getFile($name));
		
		ftruncate($filePointer, 0);      // truncate file
		rewind($filePointer);
		fwrite($filePointer, serialize($data));
		fflush($filePointer);            // flush output before releasing the lock
		flock($filePointer, LOCK_UN);    // release the lock
		fclose($filePointer);
		
		return true;
	}
	
	public function clear($name)
	{
		@unlink(self::getFile($name));
	}
	
	public function cleanup($before)
	{
		if(!FILE_SESSION_DIR) return;
		
		$files = @glob(rtrim(FILE_SESSION_DIR,'\\/'). DIRECTORY_SEPARATOR . self::$token . '*');
		if(!$files) return true;
		foreach($files as $file) {
			if (filemtime($file) < $before) @unlink($file);
		}
	}

	protected static function getFile($name)
	{
		if(!file_exists(FILE_SESSION_DIR)) mkdir(FILE_SESSION_DIR);
		
		return rtrim(FILE_SESSION_DIR,'\\/') . DIRECTORY_SEPARATOR . self::tokenize($name);
	}
	
	protected static function getFilePointer($file)
	{
		if(!file_exists($file)) file_put_contents($file, '');
		
		$key = md5($file);
		
		self::$filePointers[$key] = self::$filePointers[$key]?? fopen($file, 'r+');
		
		return self::$filePointers[$key];
	}
}

class EpesiSessionDBStorage extends EpesiSessionStorage 
{
	public function read($name)
	{
		$ret = DB::GetCol('SELECT 
								data 
							FROM 
								session 
							WHERE 
								name = %s AND 
								expires > %d' . (READ_ONLY_SESSION? '': ' FOR UPDATE'), [$name, time() - $this->getLifetime()]);
		
		return $ret? unserialize($ret[0]): '';
	}
	
	public function write($name, $data)
	{
		$data = serialize($data);

		return (bool) DB::Replace('session', [
				'expires' => time(),
				'data' => DB::is_mysql()? DB::qstr($data): '\''.DB::BlobEncode($data).'\'',
				'name' => DB::qstr($name)
		], 'name');
	}
	
	public function clear($name)
	{
		DB::Execute('DELETE FROM session WHERE name=%s', [$name]);
	}	
}

class EpesiSessionMemcachedStorage extends EpesiSessionStorage 
{
	protected static $token = MEMCACHE_SESSION_TOKEN;
	
	private $memcached;
	private $mcd = false;
	private $lockTime;	
	
	public static function active()
	{
		return MEMCACHE_SESSION_SERVER? true: false;		
	}
	
	public function __construct($lifetime)
	{
		parent::__construct($lifetime);
		
		if(extension_loaded('memcached')) {
			$this->memcached = new Memcached();
			$this->mcd = true;
		} elseif(extension_loaded('memcache')) {
			$this->memcached = new Memcache();
		} else {
			trigger_error('Missing memcache PHP extension',E_USER_ERROR);
		}
		
		$this->lockTime = time() + (ini_get('max_execution_time')?: 60);
		
		$srv = explode(':', MEMCACHE_SESSION_SERVER, 2);
			
		if(!$this->memcached->addServer($srv[0], $srv[1]?? 11211))
			trigger_error('Cannot connect to memcache server',E_USER_ERROR);
	}
	
	public function read($name)
	{
		$key = self::tokenize($name);
		
		if(!READ_ONLY_SESSION && !$this->lock($key))
			trigger_error('Unable to get lock on session mem=' . $name, E_USER_ERROR);
			
		$ret = '';
		for($i=0;; $i++) {
			$rr = $this->get($key . '/' . $i);
			if ($rr==='' || $rr===false || $rr===null) break;
			$ret .= $rr;
		}
		
		return $ret? unserialize($ret): '';
	}
	
	public function write($name, $data)
	{
		$key = self::tokenize($name);
		
		if (!$this->isLocked($key)) $this->lock($key);
		
		$data = str_split(serialize($data), 1000000); //something little less then 1MB
		$data[] = '';
		foreach($data as $i => $d) {
			$this->memcached->set($key . '/' . $i, $d, $this->getLifetime());
		}
		
		$this->unlock($key);
				
		return true;
	}	
	
	public function clear($name)
	{
		for ($k=0;;$k++) {
			if (!$this->memcached->delete(self::tokenize($name) . '/' . $k)) break;
		}			
		
		$this->unlock(self::tokenize($name));
	}
	
	public function add($key, $var, $exp=null)
	{
		if ($this->mcd) return $this->memcached->add($key, $var, $exp);
		
		return $this->memcached->add($key, $var, null, $exp);
	}
	
	public function set($key, $var, $exp=null)
	{
		if ($this->mcd) return $this->memcached->set($key, $var, $exp);
		
		return $this->memcached->set($key, $var, null, $exp);
	}
	
	public function isLocked($key)
	{
		$key .= '#lock';
		$exp = $this->lockTime;
		
		$v = $this->memcached->get($key);
		
		return $v==$exp || ($exp===null && $v);
	}
	
	public function lock($key)
	{
		$key .= '#lock';
		$exp = $this->lockTime;

		while(!$this->add($key, $exp, $exp)) { // || $this->memcached->get($key) != $exp) {
			if(time() > $exp) return false;
			usleep(100);
		}
		
		return true;
	}
	
	public function unlock($key)
	{
		$this->memcached->delete($key.'#lock');
	}
	
	public function __call($method, $args)
	{
		return call_user_func_array([$this->memcached, $method], $args);
	}
}

// remember that even with SET_SESSION = false, class defined below is declared
if(!SET_SESSION) {
    if(!isset($_SESSION) || !is_array($_SESSION))
    	$_SESSION = [];
    return;
}

if(defined('EPESI_PROCESS')) {
    ini_set('session.gc_divisor', 100);
    ini_set('session.gc_probability', 30); // FIXDEADLOCK - set to 1
} else {
    ini_set('session.gc_probability', 0);
}

session_set_save_handler(EpesiSession::create());

if(extension_loaded('apc') || extension_loaded('eaccelerator') || extension_loaded('xcache')) //fix for class DBSession not found
    register_shutdown_function('session_write_close');

if(!defined('CID')) {
    if(isset($_SERVER['HTTP_X_CLIENT_ID']) && is_numeric($_SERVER['HTTP_X_CLIENT_ID']))
        define('CID', (int)$_SERVER['HTTP_X_CLIENT_ID']);
    else
        trigger_error('Invalid request without client id');
}

session_name(ini_get('session.name'));
session_set_cookie_params(0,EPESI_DIR);
session_start();
