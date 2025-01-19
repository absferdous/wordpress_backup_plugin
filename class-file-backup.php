<?php

class bsr_File_Backup {

    public function file_backup() {
        // Define the backup directory
        $backup_dir = wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . 'my_backups_new';

        // Ensure the directory exists
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }

        // Create the zip file name
        $zip_file = $backup_dir . '/file_backup_' . current_time('Y-m-d_H-i-s') . '.zip';

        // Directories to back up
        $files_to_backup = [
            WP_CONTENT_DIR . '/uploads', // Media files
            WP_CONTENT_DIR . '/themes',  // Themes
            WP_CONTENT_DIR . '/plugins', // Plugins
        ];

        // Create a zip archive
        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE) === true) {
            foreach ($files_to_backup as $folder) {
                $this->add_folder_to_zip($folder, $zip);
            }
            $zip->close();
        } else {
            return 'Could not create file backup zip.';
        }

        return $zip_file;
    }

    private function add_folder_to_zip($folder, &$zip, $base_folder = '') {
        $files = scandir($folder);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $file_path = $folder . DIRECTORY_SEPARATOR . $file;
            $relative_path = $base_folder . DIRECTORY_SEPARATOR . $file;

            if (is_dir($file_path)) {
                $this->add_folder_to_zip($file_path, $zip, $relative_path);
            } else {
                $zip->addFile($file_path, $relative_path);
            }
        }
    }
}
