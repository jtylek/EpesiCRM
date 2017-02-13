<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Class Utils_FileStorage_Patch_RewriteTables
 */
class Utils_FileStorage_Patch_RewriteTables
{

    public static function run()
    {
        $x = new self();
        $x->execute();
    }

    public function execute()
    {
        $this->createNewTables();
        $this->rewriteAttachmentFiles();
        $this->rewriteOtherFilesWithLink();
        $this->rewriteRest();
        $this->dropOldTables();
    }

    protected function createNewTables()
    {
        $cp = Patch::checkpoint('newTables');
        if (false && !$cp->is_done()) {
            PatchUtil::db_add_column('utils_filestorage_files', 'deleted', 'I1 DEFAULT 0');
            DB::CreateTable('utils_filestorage', '
                id I8 AUTO KEY,
                filename C(256) NOTNULL,
                link C(128),
                backref C(128),
                created_on T NOTNULL,
                created_by I8 NOTNULL,
                deleted I1 DEFAULT 0,
                file_id I8 NOTNULL',
			    array('constraints'=>', FOREIGN KEY (file_id) REFERENCES utils_filestorage_files(id)')
            );
            $cp->done();
        }
        return true;
    }

    protected function rewriteAttachmentFiles()
    {
        $cp = Patch::checkpoint('rewrite_af');
        if (!$cp->is_done()) {
            $lastId = $cp->get('last_id', 0);
            $sql = 'SELECT af.*, ' . DB::Concat("'attachment_file/'", 'af.id') . ' as link'
                   . ' FROM utils_attachment_file af WHERE id>%d';

            while (false != ($files = DB::GetAll($sql, array($lastId)))) {
                self::log("--- Attachment files - next chunk! FROM ID: $lastId ---");
                foreach ($files as $file) {
                    $newFileRecord = [
                        'link' => $file['link'],
                        'backref' => 'utils_attachment/' . $file['attach_id'],
                        'filename' => $file['original'],
                        'file_id' => $file['filestorage_id'],
                        'created_on' => $file['created_on'],
                        'created_by' => $file['created_by'],
                        'deleted' => $file['deleted'],
                    ];
                    self::log('CREATE NEW FILE RECORD: ' . json_encode($newFileRecord));
                    DB::AutoExecute('utils_filestorage', $newFileRecord);
                    self::log('DELETE LINK: ' . $file['link']);
                    DB::Execute('DELETE FROM utils_filestorage_link WHERE link=%s', array($file['link']));
                    $lastId = $file['id'];
                    $cp->set('last_id', $lastId);
                }
            }
            $cp->done();
        }
        return true;
    }

    protected function rewriteOtherFilesWithLink()
    {
        $cp = Patch::checkpoint('rewrite_other_with_links');
        if (!$cp->is_done()) {
            $sql = 'SELECT f.*, l.link FROM utils_filestorage_link l LEFT JOIN utils_filestorage_files f ON l.storage_id=f.id';
            $files = DB::GetAll($sql);
            if (empty($files)) {
                self::log('THERE IS NO OTHER LINKS');
            } else {
                self::log('PROCESS OTHER LINKS');
            }
            foreach ($files as $file) {
                $newFileRecord = [
                    'link' => $file['link'],
                    'filename' => $file['filename'],
                    'file_id' => $file['id'],
                    'created_on' => $file['uploaded_on'],
                    'created_by' => 1 // we don't know who created
                ];
                self::log('CREATE NEW FILE RECORD: ' . json_encode($newFileRecord));
                DB::AutoExecute('utils_filestorage', $newFileRecord);
                self::log('DELETE LINK: ' . $file['link']);
                DB::Execute('DELETE FROM utils_filestorage_link WHERE link=%s', array($file['link']));
            }
            $cp->done();
        }
        return true;
    }

    protected function rewriteRest()
    {
        $cp = Patch::checkpoint('rewrite_rest');
        if (!$cp->is_done()) {
            $sql = 'SELECT * FROM utils_filestorage_files WHERE id NOT IN (SELECT file_id FROM utils_filestorage)';
            $files = DB::GetAll($sql);
            if (empty($files)) {
                self::log('ALL FILES PROCESSED');
            } else {
                self::log('THERE ARE SOME MORE FILES WITHOUT LINK AND NOT FROM attachmets');
            }
            foreach ($files as $file) {
                $newFileRecord = [
                    'filename' => $file['filename'],
                    'file_id' => $file['id'],
                    'created_on' => $file['uploaded_on'],
                    'created_by' => 1 // we don't know who created
                ];
                self::log('CREATE NEW FILE RECORD: ' . json_encode($newFileRecord));
                DB::AutoExecute('utils_filestorage', $newFileRecord);
            }
            $cp->done();
        }
        return true;
    }

    protected function dropOldTables()
    {
        self::log('DROP filestorage_link TABLE');
        $tablesToDrop = array('utils_filestorage_link');
        $allTables = DB::MetaTables();
        foreach ($tablesToDrop as $table) {
            if (in_array($table, $allTables)) {
                DB::DropTable($table);
            }
        }

        self::log('DROP uploaded_on COLUMN FROM utils_filestorage_files');
        PatchUtil::db_drop_column('utils_filestorage_files', 'uploaded_on');
        self::log('DROP filename COLUMN FROM utils_filestorage_files');
        PatchUtil::db_drop_column('utils_filestorage_files', 'filename');
    }

    protected function log($msg)
    {
        $msg .= "\n";
        epesi_log($msg, 'filestorage_rewrite.log');
    }
}

Utils_FileStorage_Patch_RewriteTables::run();
