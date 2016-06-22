<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');


require_once('database.php');

class DBSession {
    const MAX_SESSION_ID_LENGTH = 128;
    private static $lifetime;
    private static $memcached;
    private static $memcached_lock_time;
    private static $session_fp;
    private static $session_client_fp;
    private static $session_type;

    public static function truncated_session_id($session_id = null)
    {
        if ($session_id === null) {
            $session_id = session_id();
        }
        return substr($session_id, 0, self::MAX_SESSION_ID_LENGTH);
    }

    public static function open($path, $name) {
        self::$lifetime = min(ini_get("session.gc_maxlifetime"),2592000-1); //less then 30 days
        switch(SESSION_TYPE) {
            case 'file':
            case 'sql':
                self::$session_type = SESSION_TYPE;
                break;
            case 'memcache':
                if(MEMCACHE_SESSION_SERVER) {
                    $srv = explode(':',MEMCACHE_SESSION_SERVER,2);
                    self::$memcached = new EpesiMemcache();
                    
                    if(!self::$memcached->addServer($srv[0],(isset($srv[1])?$srv[1]:11211)))
                        trigger_error('Cannot connect to memcache server',E_USER_ERROR);
                }
                if(self::$memcached) self::$session_type = SESSION_TYPE;
                else self::$session_type = 'file';
                break;
            default:
                self::$session_type = 'file';
        }
        if(self::$session_type=='memcache') {
            self::$memcached_lock_time = ini_get("max_execution_time");
            if(!self::$memcached_lock_time) self::$memcached_lock_time = 60;
            self::$memcached_lock_time += time();
        }
        return true;
    }

    public static function close() {
        //self::gc(self::$lifetime);
        return true;
    }

    public static function read($name) {
        $name = self::truncated_session_id($name);
        
        //main session
        switch(self::$session_type) {
            case 'file':
                if(!file_exists(FILE_SESSION_DIR)) mkdir(FILE_SESSION_DIR);
                $sess_file = rtrim(FILE_SESSION_DIR,'\\/').'/'.FILE_SESSION_TOKEN.$name;
                if(!file_exists($sess_file)) file_put_contents($sess_file,'');
                self::$session_fp = fopen($sess_file,'r+');
                if(!READ_ONLY_SESSION && !flock(self::$session_fp,LOCK_EX)) 
                    trigger_error('Unable to get lock on session file='.$sess_file,E_USER_ERROR);
                $ret = stream_get_contents(self::$session_fp);
                break;
            case 'memcache':
                if(!READ_ONLY_SESSION && !self::$memcached->lock(MEMCACHE_SESSION_TOKEN.$name,self::$memcached_lock_time))
                    trigger_error('Unable to get lock on session mem='.$name,E_USER_ERROR);
                $ret = '';
                for($i=0;; $i++) {
                    $rr = self::$memcached->get(MEMCACHE_SESSION_TOKEN.$name.'/'.$i);
                    if($rr==='' || $rr===false || $rr===null) break;
                    $ret .= $rr;
                }
                break;
            case 'sql':
                $ret = DB::GetCol('SELECT data FROM session WHERE name = %s AND expires > %d'.(READ_ONLY_SESSION?'':' FOR UPDATE'), array($name, time()-self::$lifetime));
                if($ret) $ret = $ret[0];
                break;
        }
        if($ret) $_SESSION = unserialize($ret);

        if(CID!==false) {
            if(!is_numeric(CID))
                trigger_error('Invalid client id.',E_USER_ERROR);

            if(isset($_SESSION['session_destroyed'][CID])) return '';
            
            switch(self::$session_type) {
                case 'file':
                    $sess_file = rtrim(FILE_SESSION_DIR,'\\/').'/'.FILE_SESSION_TOKEN.$name.'_'.CID;
                    if(!file_exists($sess_file)) file_put_contents($sess_file,'');
                    self::$session_client_fp = fopen($sess_file,'r+');
                    if(!READ_ONLY_SESSION && !flock(self::$session_client_fp,LOCK_EX)) 
                        trigger_error('Unable to get lock on session file='.$sess_file,E_USER_ERROR);
                    $ret = stream_get_contents(self::$session_client_fp);
                    break;
                case 'memcache':
                    if(!READ_ONLY_SESSION && !self::$memcached->lock(MEMCACHE_SESSION_TOKEN.$name.'_'.CID,self::$memcached_lock_time))
                        trigger_error('Unable to get lock on session mem='.$name.'_'.CID,E_USER_ERROR);
                    $ret = '';
                    for($i=0;; $i++) {
                        $rr = self::$memcached->get(MEMCACHE_SESSION_TOKEN.$name.'_'.CID.'/'.$i);
                        if($rr==='' || $rr===false || $rr===null) break;
                        $ret .= $rr;
                    }
                    break;
                case 'sql':
                    $ret = DB::GetCol('SELECT data FROM session_client WHERE session_name = %s AND client_id=%d'.(READ_ONLY_SESSION?'':' FOR UPDATE'), array($name, CID));
                    if($ret) $ret = $ret[0];
                    break;
            }
            if($ret) $_SESSION['client'] = unserialize($ret);

            if(!isset($_SESSION['client']['__module_vars__']))
                $_SESSION['client']['__module_vars__'] = array();
        }
        return '';
    }

    public static function write($name, $data) {
        if(READ_ONLY_SESSION || defined('SESSION_EXPIRED')) return true;
        $name = self::truncated_session_id($name);
        $ret = 1;
        if(CID!==false && isset($_SESSION['client']) && !isset($_SESSION['session_destroyed'][CID])) {
            $data = serialize($_SESSION['client']);
            
            switch(self::$session_type) {
                case 'file':
                    ftruncate(self::$session_client_fp, 0);      // truncate file
                    rewind(self::$session_client_fp);
                    fwrite(self::$session_client_fp, $data);
                    fflush(self::$session_client_fp);            // flush output before releasing the lock
                    flock(self::$session_client_fp, LOCK_UN);    // release the lock
                    fclose(self::$session_client_fp);
                    break;
                case 'memcache':
                    if(self::$memcached->is_lock(MEMCACHE_SESSION_TOKEN.$name.'_'.CID,self::$memcached_lock_time)) {
                        $data = str_split($data,1000000); //something little less then 1MB
                        $data[] = '';
                        foreach($data as $i=>$d) {
                            self::$memcached->set(MEMCACHE_SESSION_TOKEN.$name.'_'.CID.'/'.$i, $d, self::$lifetime);
                        }
                        self::$memcached->unlock(MEMCACHE_SESSION_TOKEN.$name.'_'.CID);
                    }
                    break;
                case 'sql':
                    if(DB::is_mysql())
                        $data = DB::qstr($data);
                    else
                        $data = '\''.DB::BlobEncode($data).'\'';
                    $ret &= (bool) DB::Replace('session_client',array('data'=>$data,'session_name'=>DB::qstr($name),'client_id'=>CID),array('session_name','client_id'));
                    break;
            }
        }
        if(isset($_SESSION['client'])) unset($_SESSION['client']);
        $data = serialize($_SESSION);
        switch(self::$session_type) {
            case 'file':
                ftruncate(self::$session_fp, 0);      // truncate file
                rewind(self::$session_fp);
                fwrite(self::$session_fp, $data);
                fflush(self::$session_fp);            // flush output before releasing the lock
                flock(self::$session_fp, LOCK_UN);    // release the lock
                fclose(self::$session_fp);
                $ret &= (bool) DB::Replace('session',array('expires'=>time(),'name'=>DB::qstr($name)),'name');
                break;
            case 'memcache':
                if(self::$memcached->is_lock(MEMCACHE_SESSION_TOKEN.$name,self::$memcached_lock_time)) {
                    $data = str_split($data,1000000); //something little less then 1MB
                    $data[] = '';
                    foreach($data as $i=>$d) {
                        self::$memcached->set(MEMCACHE_SESSION_TOKEN.$name.'/'.$i, $d, self::$lifetime);
                    }
                    self::$memcached->unlock(MEMCACHE_SESSION_TOKEN.$name);
                    $ret &= (bool) DB::Replace('session',array('expires'=>time(),'name'=>DB::qstr($name)),'name');
                }
                break;
            case 'sql':
                if(DB::is_mysql())
                    $data = DB::qstr($data);
                else
                    $data = '\''.DB::BlobEncode($data).'\'';
                $ret &= (bool) DB::Replace('session',array('expires'=>time(),'data'=>$data,'name'=>DB::qstr($name)),'name');
                break;
        }

        return ($ret>0)?true:false;
    }
    
    public static function destroy_client($name,$i) {
        $name = self::truncated_session_id($name);
        switch(self::$session_type) {
            case 'file':
                $sess_file = rtrim(FILE_SESSION_DIR,'\\/').'/'.FILE_SESSION_TOKEN.$name.'_'.$i;
                @unlink($sess_file);
                break;
            case 'memcache':
                for($k=0;;$k++)
                    if(!self::$memcached->delete(MEMCACHE_SESSION_TOKEN.$name.'_'.$i.'/'.$k)) break;
                break;
            case 'sql':
                DB::Execute('DELETE FROM session_client WHERE session_name=%s AND client_id=%d',array($name,$i));
                break;
        }
        DB::Execute('DELETE FROM history WHERE session_name=%s AND client_id=%d',array($name,$i));
    }

    public static function destroy($name) {
        $name = self::truncated_session_id($name);
        $cids = DB::GetCol('SELECT DISTINCT client_id FROM history WHERE session_name=%s',array($name));
        foreach($cids as $i)
            self::destroy_client($name,$i);
        
        switch(self::$session_type) {
            case 'file':
                $sess_file = rtrim(FILE_SESSION_DIR,'\\/').'/'.FILE_SESSION_TOKEN.$name;
                @unlink($sess_file);
                break;
            case 'memcache':
                for($k=0;;$k++)
                    if(!self::$memcached->delete(MEMCACHE_SESSION_TOKEN.$name.'/'.$k)) break;
                break;
        }
        DB::BeginTrans();
        DB::Execute('DELETE FROM history WHERE session_name=%s',array($name));
        DB::Execute('DELETE FROM session_client WHERE session_name=%s',array($name));
        DB::Execute('DELETE FROM session WHERE name=%s',array($name));
        DB::CommitTrans();
        return true;
    }

    public static function gc($lifetime) {
        $t = time()-$lifetime;
        $ret = DB::Execute('SELECT name FROM session WHERE expires <= %d',array($t));
        while($row = $ret->FetchRow()) {
            self::destroy($row['name']);
        }

        if(FILE_SESSION_DIR) {
            $files = @glob(rtrim(FILE_SESSION_DIR,'\\/').'/'.FILE_SESSION_TOKEN.'*');
            if(!$files) return;
            foreach($files as $sess_file) {
                if(filemtime($sess_file)<$t) @unlink($sess_file);
            }
        }
        return true;
    }
}

class EpesiMemcache {
    private $memcached = null;
    private $mcd = false;
    
    public function __construct() {
        if(extension_loaded('memcached')) {
            $this->memcached = new Memcached();
            $this->mcd = true;
        } elseif(extension_loaded('memcache')) {
            $this->memcached = new Memcache();
        } else {
            trigger_error('Missing memcache PHP extension',E_USER_ERROR);
        }
    }
    
    public function add($key,$var,$exp=null) {
        if($this->mcd) return $this->memcached->add($key,$var,$exp);
        return $this->memcached->add($key,$var,null,$exp);
    }
    
    public function set($key,$var,$exp=null) {
        if($this->mcd) return $this->memcached->set($key,$var,$exp);
        return $this->memcached->set($key,$var,null,$exp);
    }

    public function is_lock($key,$exp=null) {
        $key .= '#lock';
        $v = $this->memcached->get($key);
        return $v==$exp || ($exp===null && $v);
    }
    
    public function lock($key,$exp) {
        $key .= '#lock';
        while(!$this->add($key,$exp,$exp) || $this->memcached->get($key)!=$exp) {
            if(time()>$exp) return false;
            usleep(100);
        }
        return true;
    }
    
    public function unlock($key) {
        $this->memcached->delete($key.'#lock');
    }
    
    public function __call($f,$a) {
        return call_user_func_array(array($this->memcached,$f),$a);
    }
}

// remember that even with SET_SESSION = false, class defined below is declared
if(!SET_SESSION) {
    global $_SESSION;
    if(!isset($_SESSION) || !is_array($_SESSION))
    	$_SESSION = array();
    return;
}

if(defined('EPESI_PROCESS')) {
    ini_set('session.gc_divisor', 100);
    ini_set('session.gc_probability', 30); // FIXDEADLOCK - set to 1
} else {
    ini_set('session.gc_probability', 0);
}
ini_set('session.save_handler', 'user');

session_set_save_handler(array('DBSession','open'),
                             array('DBSession','close'),
                             array('DBSession','read'),
                             array('DBSession','write'),
                             array('DBSession','destroy'),
                             array('DBSession','gc'));

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
