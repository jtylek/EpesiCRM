<?php

class BackupUtil {

    private $_backup_dir;
    private $_backup_store;
    private $_epesi_dir;
    private $_prev_dir;

    public function __construct($epesi_dir, $backup_dir) {
        $this->_epesi_dir = $epesi_dir;
        $this->_backup_dir = $backup_dir;
        $this->_backup_store = new BackupStore($backup_dir);
    }

    function list_backups() {
        $files = $this->_backup_store->list_backup_files();
        $ret = array();
        foreach ($files as $f) {
            $ret[] = new Backup($f);
        }
        return $ret;
    }

    function create_backup_of_epesi() {
        $description = "EPESI ver " . EPESI_VERSION . " rev " . EPESI_REVISION;
        return $this->_create_backup('.', $description);
    }

    function create_backup($files, $description = '') {
        return $this->_create_backup($files, $description);
    }

    private function _create_backup($files, $description) {
        $file = $this->_backup_store->new_backup_file();
        $bkp = new Backup($file);
        $this->_chdir_to_epesi();
        $success = $bkp->create($files, $description, $this->_backup_dir);
        $this->_chdir_back();
        return $success ? $bkp : false;
    }

    private function _chdir_to_epesi() {
        if ($this->_epesi_dir) {
            $this->_prev_dir = getcwd();
            chdir($this->_epesi_dir);
        }
    }

    private function _chdir_back() {
        if ($this->_prev_dir) {
            chdir($this->_prev_dir);
        }
    }

    static function default_instance() {
        return new BackupUtil(getcwd(), 'data/backups');
    }

}

class BackupStore {

    private $_extension = 'bkp';
    private $_dir;

    public function __construct($dir) {
        $this->_dir = $dir;
        if (file_exists($dir) && !is_dir($dir))
            throw new ErrorException("Backup path '$dir' is not directory");
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
            file_put_contents($dir . '/index.html', '');
        }
        if (!is_dir($dir))
            throw new ErrorException("Backup directory($dir) doesn't exist");
        if (!is_writable($dir))
            throw new ErrorException("Backup directory($dir) is not writable");
    }

    public function new_backup_file() {
        $file = null;
        while ($file == null || file_exists($file)) {
            // unix timestamp with 2 digits
            $name = sprintf("%d", microtime(true) * 100);
            $file = $this->_dir . DIRECTORY_SEPARATOR . "$name.{$this->_extension}";
        }
        return $file;
    }

    public function list_backup_files() {
        $ret = array();
        $dir = new DirectoryIterator($this->_dir);
        foreach ($dir as $file) {
            if ($file->getExtension() == $this->_extension)
                $ret[] = $file->getPathname();
        }
        return $ret;
    }

}

class Backup {

    private static $__properties_in_metadata = array('date', 'description');
    private $date;
    private $description;
    private $files;
    private $_overwrite;

    /** @var BackupArchive */
    private $_archive;

    public function __construct($backup_file, $overwrite = false) {
        $this->_file = $backup_file;
        $this->_overwrite = $overwrite;
        $this->_archive = new BackupArchive($backup_file);
        $this->_read_metadata();
    }

    private function _read_metadata() {
        $data = $this->_archive->get_metadata();
        $this->_set_properties($data);
    }

    private function _get_properties() {
        $data = array();
        foreach (self::$__properties_in_metadata as $p) {
            $data[$p] = $this->$p;
        }
        return $data;
    }

    private function _set_properties($metadata) {
        if (!is_array($metadata))
            return;
        foreach (self::$__properties_in_metadata as $p) {
            if (isset($metadata[$p]))
                $this->$p = $metadata[$p];
        }
    }

    public function create($files, $description, $exclude_files = array()) {
        set_time_limit(0);
        if ($this->_overwrite == false && $this->_archive->exists())
            return false;

        $success = $this->_archive->create($files, $exclude_files);
        if ($success) {
            $this->date = time();
            $this->description = $description;
            $this->_archive->set_metadata($this->_get_properties());
        }
        return $success;
    }

    public function restore_to($destination) {
        set_time_limit(0);
        return $this->_archive->extractTo($destination);
    }
    
    public function restore() {
        return $this->restore_to('.');
    }

    public function get_date($format = null) {
        if (is_string($format))
            return date($format, $this->date);
        return $this->date;
    }

    public function get_description() {
        return $this->description;
    }

    public function get_files() {
        if (!$this->files)
            $this->files = $this->_archive->list_files();
        return $this->files;
    }

}

class BackupArchive extends ZipArchive {

    private $_file;
    private $_exclude = array();

    public function __construct($file) {
        $this->_file = $file;
    }

    private function _open_create() {
        $flag = file_exists($this->_file) ? ZipArchive::OVERWRITE : ZipArchive::CREATE;
        $this->_open($flag);
    }

    private function _open($flag = ZipArchive::CREATE) {
        $open_status = $this->open($this->_file, $flag);
        if ($open_status !== true)
            throw new ErrorException("File: {$this->_file} open error code: " . $open_status);
    }

    public function create($files, $exclude = array()) {
        $this->_open_create();

        $this->_exclude = is_array($exclude) ? $exclude : array($exclude);
        if (!is_array($files))
            $files = array($files);
        foreach ($files as $f) {
            $this->addRecursive($f);
        }

        return $this->close();
    }
    
    public function extractTo($destination, $entries = null) {
        $this->_open();
        return parent::extractTo($destination, $entries);
    }

    public function exists() {
        return file_exists($this->_file);
    }

    private function addRecursive($file) {
        if (is_file($file))
            return $this->addFile($file);

        if (is_dir($file)) {
            $ret = true;
            $rdi = new RecursiveDirectoryIterator($file,
                            FilesystemIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator(
                            $rdi, RecursiveIteratorIterator::SELF_FIRST);
            foreach ($files as $f) {
                $succ = $this->add($f);
                $ret &= $succ;
            }
            return $ret;
        }
    }

    private function add($file_info) {
        static $counter = 0; // this counter is used to avoid to many opened files error

        if ($counter == 1000) {
            $this->close();
            $this->_open();
            $counter = 0;
        }

        $path = $this->_remove_first_dot($file_info->getPathname());
        if ($this->_is_excluded($path))
            return true;
        if ($file_info->isLink())
            return true;
        $counter += 1;
        if ($file_info->isDir())
            return $this->addEmptyDir($path);
        if ($file_info->isFile())
            return $this->addFile($path);
    }

    public function get_metadata() {
        if (!file_exists($this->_file))
            return null;
        $this->_open();
        $comment = $this->getArchiveComment();
        $this->close();
        if ($comment === false)
            return; // maybe throw some error
        $data = @unserialize($comment);
        if ($data === false)
            return; // maybe throw some error
        return $data;
    }

    public function set_metadata($value) {
        $this->_open();
        $ret = $this->setArchiveComment(serialize($value));
        $this->close();
        return $ret;
    }

    public function list_files($sort = true) {
        $files = array();
        $this->_open();
        for ($i = 0; $i < $this->numFiles; $i++) {
            $stat = $this->statIndex($i);
            $files[] = $stat['name'];
        }
        $this->close();
        if ($sort)
            sort($files);
        return $files;
    }

    private function _is_excluded($file) {
        foreach ($this->_exclude as $ex) {
            if (strpos($file, $ex) === 0)
                return true;
        }
        return false;
    }

    private function _remove_first_dot($path) {
        if ($path[0] == '.' && ($path[1] == '/' || $path[1] == '\\'))
            return substr($path, 2);
        return $path;
    }

}

?>
