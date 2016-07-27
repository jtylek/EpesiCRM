<?php
/**
 * 
 * @author pbukowski@telaxus.com
 * @copyright Telaxus LLC
 * @license MIT
 * @version 0.1
 * @package epesi-Utils
 * @subpackage FileStorage
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_FileStorageCommon extends ModuleCommon {

    private static function get_storage_file_path($hash) {
        $dirs = str_split(substr($hash,0,5));
        $path = self::Instance()->get_data_dir().implode(DIRECTORY_SEPARATOR,$dirs);
        @mkdir($path,0770,true);
        return $path.DIRECTORY_SEPARATOR.substr($hash,5);
    }
    
    public static function write_content($filename,$content,$link='') {
        $hash = hash('sha512',$content);
        $path = self::get_storage_file_path($hash);
        if(file_exists($path)) {
            $id = DB::GetOne('SELECT id FROM utils_filestorage_files WHERE hash=%s',array($hash));
        } else {
            file_put_contents($path,$content);
            DB::Execute('INSERT INTO utils_filestorage_files(filename,uploaded_on,hash) VALUES(%s,%T,%s)',array($filename,time(),$hash));
            $id = DB::Insert_ID('utils_filestorage_files','id');
        }
        if(!$id) throw new Utils_FileStorage_WriteError('Exception - write error.');
        if($link) self::add_link($link,$id);
        return $id;
    }
    
    public static function write_file($filename,$file,$link='') {
        $hash = hash_file('sha512',$file);
        $path = self::get_storage_file_path($hash);
        if(file_exists($path)) {
            $id = DB::GetOne('SELECT id FROM utils_filestorage_files WHERE hash=%s',array($hash));
        } else {
            copy($file,$path);
            DB::Execute('INSERT INTO utils_filestorage_files(filename,uploaded_on,hash) VALUES(%s,%T,%s)',array($filename,time(),$hash));
            $id = DB::Insert_ID('utils_filestorage_files','id');
        }
        if(!$id) throw new Utils_FileStorage_WriteError();
        if($link) self::add_link($link,$id);
        return $id;
    }

    public static function meta($id, $use_cache=true) {
        static $meta_cache = array();
        if(!is_numeric($id)) $id = self::get_storage_id_by_link($id);
        if($use_cache && isset($meta_cache[$id])) return $meta_cache[$id];
        
        $meta = DB::GetRow('SELECT * FROM utils_filestorage_files WHERE id=%d',array($id));
        if(!$meta) throw new Utils_FileStorage_StorageNotFound('Exception - DB storage object not found: '.$id);
        $meta['file'] = self::get_storage_file_path($meta['hash']);
        if(!file_exists($meta['file'])) throw new Utils_FileStorage_FileNotFound('Exception - file not found: '.$meta['file']);
        $meta['links'] = DB::GetCol('SELECT link FROM utils_filestorage_link WHERE storage_id=%d',array($id));
        $meta_cache[$id] = $meta;
        return $meta;
    }
    
    public static function read_content($id) {
        $meta = self::meta($id);
        return file_get_contents($meta['file']);
    }
    
    public static function add_link($link,$id) {
        try {
            $id2 = self::get_storage_id_by_link($link,false);
            if($id2!=$id) {
                throw new Utils_FileStorage_LinkDuplicate($link);
            }
        } catch(Utils_FileStorage_LinkNotFound $e) {
            DB::Execute('INSERT INTO utils_filestorage_link(storage_id,link) VALUES(%d,%s)',array($id,$link));
        }
    }

    public static function update_link($link,$id) {
        try {
            self::add_link($link,$id);
        } catch(Utils_FileStorage_LinkDuplicate $e) {
            $id2 = self::get_storage_id_by_link($link);
            DB::Execute('UPDATE utils_filestorage_link SET storage_id=%d WHERE link=%s',array($id,$link));
            self::delete($id2);
            self::get_storage_id_by_link($link,false); //update cache
        }
    }

    public static function get_storage_id_by_link($link,$use_cache=true) {
        static $cache = array();
        if(!$use_cache || !isset($cache[$link])) {
            $cache[$link] = DB::GetOne('SELECT storage_id FROM utils_filestorage_link WHERE link=%s',array($link));
            if(!$cache[$link]) throw new Utils_FileStorage_LinkNotFound($link);
        }
        return $cache[$link];
    }

    public static function delete($id) {
        DB::StartTrans();
        if(!is_numeric($id)) {
            $mid = self::get_storage_id_by_link($id,false);
            DB::Execute('DELETE FROM utils_filestorage_link WHERE link=%s',array($id));
            $id = $mid;
        }
        if(DB::GetOne('SELECT 1 FROM utils_filestorage_link WHERE storage_id=%d',array($id))) return;
        $meta = self::meta($id);
        DB::Execute('DELETE FROM utils_filestorage_files WHERE id=%d',array($id));
        DB::CompleteTrans();
        @unlink($meta['file']);
        for($i=0; $i<=4; $i++) {
            $meta['file'] = dirname($meta['file']);
            if(!@rmdir($meta['file'])) break;
        }
    }
    
    public static function get($id) {
        return new Utils_FileStorage_Object($id);
    }
}

class Utils_FileStorage_StorageNotFound extends Exception {}
class Utils_FileStorage_LinkNotFound extends Exception {}
class Utils_FileStorage_LinkDuplicate extends Exception {}
class Utils_FileStorage_FileNotFound extends Exception {}
class Utils_FileStorage_WriteError extends Exception {}

class Utils_FileStorage_Object {
    private $id;
    public function __construct($id) {
        $this->id = $id;
    }
    
    public function fp() {
        $meta = Utils_FileStorageCommon::meta($this->id);
        return fopen($meta['file'],'rb');
    }
    
    public function file() {
        $meta = Utils_FileStorageCommon::meta($this->id);
        return $meta['file'];
    }

    public function read() {
        return Utils_FileStorageCommon::read_content($this->id);
    }
    
    public function delete() {
        return Utils_FileStorageCommon::delete($this->id);
    }
    
    public function meta() {
        return Utils_FileStorageCommon::meta($this->id);
    }

    public function add_link($link) {
        $meta = Utils_FileStorageCommon::meta($this->id,false);
        return Utils_FileStorageCommon::add_link($link,$meta['id']);
    }
}

?>