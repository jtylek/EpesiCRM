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

    private static function get_storage_file($hash) {
        $dirs = str_split(substr($hash,0,5));
        $path = self::Instance()->get_data_dir().implode(DIRECTORY_SEPARATOR,$dirs);
        @mkdir($path,0770,true);
        return $path.DIRECTORY_SEPARATOR.substr($hash,5);
    }

    public static function write_content($filename,$content) {
        $hash = hash('sha512',$content);
        $path = self::get_storage_file($hash);
        if(file_exists($path)) return DB::GetOne('SELECT id FROM utils_filestorage_files WHERE hash=%s',array($hash));
        file_put_contents($path,$content);
        DB::Execute('INSERT INTO utils_filestorage_files(filename,uploaded_on,hash) VALUES(%s,%T,%s)',array($filename,time(),$hash));
        return DB::Insert_ID('utils_filestorage_files','id');
    }

    public static function write_file($filename,$file) {
        $hash = hash_file('sha512',$file);
        $path = self::get_storage_file($hash);
        if(file_exists($path)) return DB::GetOne('SELECT id FROM utils_filestorage_files WHERE hash=%s',array($hash));
        copy($file,$path);
        DB::Execute('INSERT INTO utils_filestorage_files(filename,uploaded_on,hash) VALUES(%s,%T,%s)',array($filename,time(),$hash));
        return DB::Insert_ID('utils_filestorage_files','id');
    }

    public static function read_meta($id) {
        static $meta_cache = array();
        if(isset($meta_cache[$id])) return $meta_cache[$id];
        
        $meta = DB::GetRow('SELECT * FROM utils_filestorage_files WHERE id=%d',array($id));
        if(!$meta) throw new Utils_FileStorage_RecordNotFound();
        $meta['file'] = self::get_storage_file($meta['hash']);
        if(!file_exists($meta['file'])) throw new Utils_FileStorage_FileNotFound();
        $meta_cache[$id] = $meta;
        return $meta;
    }
    
    public static function read_content($id) {
        $meta = self::read_meta($id);
        return file_get_contents($meta['file']);
    }
}

class Utils_FileStorage_RecordNotFound extends Exception {}
class Utils_FileStorage_FileNotFound extends Exception {}

?>