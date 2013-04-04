<?php

////////////////////
///  INTERFACES  ///
////////////////////

interface IChecksumCalculator {

    function calculate_file($file);

    function calculate_string($string);
}

interface IFileListFactory {

    function next_file_data();
}

interface ICleanAction {

    function perform($file, $is_dir = false);
}

interface IStatusReporter {

    function report($file, $status);

    function get_report();
}


////////////////////////////////
///  CLEANER IMPLEMENTATION  ///
////////////////////////////////

class Cleaner {
    private $checksum_calculator;
    private $clean_action;
    private $file_factory;
    private $status_reporter;

    function __construct(
        IChecksumCalculator $checksum_calculator,
        ICleanAction $clean_action,
        IFileListFactory $file_factory,
        IStatusReporter $status_reporter = null) {
        $this->checksum_calculator = $checksum_calculator;
        $this->clean_action = $clean_action;
        $this->file_factory = $file_factory;
        $this->status_reporter = $status_reporter;
    }

    public static function default_instance($file) {
        return new Cleaner(
            new NoNewLineMd5ChecksumCalculator(),
            new RemoveFileAction(),
            new FileListFactoryFromFile($file),
            new SimpleStatusReport()
        );
    }

    function clean() {
        while (($file_data = $this->file_factory->next_file_data())) {
            $path = $file_data['path'];
            $is_dir = $file_data['dir'];
            $md5sums = $file_data['md5sums'];
            $status = false;
            if ($is_dir) {
                $status = $this->clean_action->perform($path, $is_dir);
            } else {
                $md5 = $this->checksum_calculator->calculate_file($path);
                if (in_array($md5, $md5sums)) {
                    $status = $this->clean_action->perform($path);
                } else {
                    $this->report_status($path, "checksum doesn't match any of listed");
                }
            }
            $this->report_status($path, $status);
        }
        return $this->get_report();
    }

    private function report_status($file_path, $status) {
        if ($this->status_reporter)
            $this->status_reporter->report($file_path, $status);
    }

    private function get_report() {
        if ($this->status_reporter)
            return $this->status_reporter->get_report();
        return null;
    }

}


///////////////////////////////////////
///  HELPER CLASSES IMPLEMENTATION  ///
///////////////////////////////////////

class NoNewLineMd5ChecksumCalculator implements IChecksumCalculator {

    function calculate_file($file) {
        $content = file_get_contents($file);
        return $this->calculate_string($content);
    }

    function calculate_string($string) {
        $content = str_replace(array("\n", "\r"), '', $string);
        return md5($content);
    }
}

class FileListFactoryFromFile implements IFileListFactory {

    private $file;
    private $file_handle = false;
    private $opened = false;

    function __construct($file) {
        $this->file = $file;
    }

    private static function parse_line($data) {
        return unserialize($data);
    }

    function next_file_data() {
        $data = $this->read_next_line();
        if ($data === false)
            return false;
        return self::parse_line($data);
    }

    private function read_next_line() {
        // open file for reading
        if (!$this->opened) {
            $this->file_handle = fopen($this->file, 'r');
            $this->opened = true;
        }
        // check for error or method call after file was closed
        if ($this->file_handle === false)
            return false;

        $line = fgets($this->file_handle);

        // close file if error occured or EOF
        if ($line === false) {
            fclose($this->file_handle);
            $this->file_handle = false;
        }
        // trim EOL character
        return trim($line);
    }
}

class RemoveFileAction implements ICleanAction {

    function perform($file, $is_dir = false) {
        if (file_exists($file) == false)
            return true;
        if ($is_dir)
            return $this->remove_dir($file);
        return $this->remove_file($file);
    }

    private function remove_dir($dir) {
        if (!is_dir($dir))
            return "should be directory but is not";
        if (count(scandir($dir)) != 2)
            return "is not emtpy'";
        return self::remove($dir) ? true : "unable to remove directory";
    }

    private function remove_file($file) {
        return self::remove($file) ? true : "unable to delete file";
    }

    private static function remove($file) {
        return unlink($file);
    }
}

class SimpleStatusReport implements IStatusReporter {

    private $str = '';

    function report($file, $status) {
        if (is_bool($status)) {
            $status = $status ? "Success" : "Failure";
        }
        $this->str .= "$file: $status\n";
    }

    function get_report() {
        return $this->str;
    }
}
