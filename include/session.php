<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.0
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @license SPL
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

if(!SET_SESSION) return;

require_once('database.php');

class DBSession {
    private static $lifetime;
    private static $name;
    private static $ado; //doesn't work

    public static function open($path, $name) {
        self::$lifetime = ini_get("session.gc_maxlifetime");
        return true;
    }

    public static function close() {
        //self::gc(self::$lifetime);
        return true;
    }

    public static function read($name) {
	    	$ret = DB::GetOne('SELECT data FROM session WHERE name = %s AND expires > %d', array($name, time()-self::$lifetime));
		if($ret) {
		    	$_SESSION = unserialize($ret);
		}

		if(CID!==false) {
//code below need testing on postgresql - concurrent epesi execution with session blocking
/*			if(READ_ONLY_SESSION) {
				self::$ado = DB::$ado;
			} else {
				self::$ado = DB::Connect();
		  		self::$ado->BeginTrans();
		  	}
			if(is_numeric(CID) && $ret = self::$ado->GetCol('SELECT data FROM session_client WHERE session_name='.self::$ado->qstr($name).' AND client_id='.CID.' LIMIT 1 FOR UPDATE')) {
				$_SESSION['client'] = unserialize($ret[0]);
		  	}*/
 //mysql working alternative
			if(!READ_ONLY_SESSION && !DB::GetOne('SELECT GET_LOCK(%s,%d)',array($name.'_'.CID,ini_get('max_execution_time'))))
				trigger_error('Unable to get lock on session_client name='.$name.' cid='.CID,E_USER_ERROR);

			if($ret = DB::GetCol('SELECT data FROM session_client WHERE session_name=%s AND client_id=%d FOR UPDATE', array($name,CID)))
				$_SESSION['client'] = unserialize($ret[0]);
		}
		return '';
    }

    public static function write($name, $data) {
		if(READ_ONLY_SESSION) return true;
		$ret = 0;
		if(CID!==false && isset($_SESSION['client'])) {
			$data = serialize($_SESSION['client']);
//code below need testing on postgresql - concurrent epesi execution with session blocking
/*
			if(DATABASE_DRIVER=='postgres') $data = '\''.self::$ado->BlobEncode($data).'\'';
			else $data = self::$ado->qstr($data);
			$ret &= self::$ado->Replace('session_client',array('data'=>$data,'session_name'=>self::$ado->qstr($name),'client_id'=>CID),array('session_name','client_id'));
			self::$ado->CommitTrans();
			self::$ado->Close();
			unset(self::$ado);*/
 //mysql working alternative
			if(DATABASE_DRIVER=='postgres') $data = '\''.DB::BlobEncode($data).'\'';
			else $data = DB::qstr($data);
			$ret &= DB::Replace('session_client',array('data'=>$data,'session_name'=>DB::qstr($name),'client_id'=>CID),array('session_name','client_id'));
			DB::Execute('RELEASE_LOCK(%s)',array($name.'_'.CID));
			
		}
		if(isset($_SESSION['client'])) unset($_SESSION['client']);
		$data = serialize($_SESSION);
		if(DATABASE_DRIVER=='postgres') $data = '\''.DB::BlobEncode($data).'\'';
		else $data = DB::qstr($data);
		$ret &= DB::Replace('session',array('expires'=>time(),'data'=>$data,'name'=>DB::qstr($name)),'name');
		return ($ret>0)?true:false;
    }

    public static function destroy($name) {
    	DB::BeginTrans();
    	DB::Execute('DELETE FROM history WHERE session_name=%s',array($name));
    	DB::Execute('DELETE FROM session_client WHERE session_name=%s',array($name));
    	DB::Execute('DELETE FROM session WHERE name=%s',array($name));
	DB::CommitTrans();
    	return true;
    }

    public static function gc($lifetime) {
    	$t = time()-$lifetime;
	$ret = DB::Execute('SELECT name FROM session WHERE expires < %d',array($t));
	while($row = $ret->FetchRow()) {
		self::destroy($row['name']);
	}
/*		DB::Execute('DELETE FROM history WHERE session_name IN (SELECT name FROM session WHERE expires < %d)',array($t));
    	DB::Execute('DELETE FROM session_client WHERE session_name IN (SELECT name FROM session WHERE expires < %d)',array($t));
	   	DB::Execute('DELETE FROM session WHERE expires < %d',array($t));*/
        return true;
    }
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

if(!defined('CID')) {
	if(isset($_SERVER['HTTP_X_CLIENT_ID']) && is_numeric($_SERVER['HTTP_X_CLIENT_ID']))
		define('CID', (int)$_SERVER['HTTP_X_CLIENT_ID']);
	else
		trigger_error('Invalid request without client id');
}

session_set_cookie_params(0,EPESI_DIR);
session_start();
?>
