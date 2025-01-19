<?php class bsr_Database_backup {
    public function backup_database() {
        global $wpdb; // Connecting to the database

        // Define the backup directory
        $backup_dir = wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . 'my_backups_new';

        // Create the backup directory if it doesn't exist
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }

        // Create the backup file name
        $sql_file = $backup_dir . '/db_backup_' . current_time('Y-m-d_H-i-s') . '.sql';
        $zip_file = $backup_dir . '/db_backup_' . current_time('Y-m-d_H-i-s') . '.zip';

        // Get the list of all tables in the database
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);

        // Open the SQL file for writing
        $file_handle = fopen($sql_file, 'a');
        if (!$file_handle) {
            return 'Could not open file for writing';
        }

        // Iterate through each table
        foreach ($tables as $table) {
            // Get the CREATE TABLE statement
            $create = $wpdb->get_row("SHOW CREATE TABLE `$table[0]`", ARRAY_N);
            fwrite($file_handle, $create[1] . ";\n");

            // Get all rows from the current table
            $rows = $wpdb->get_results("SELECT * FROM `$table[0]`", ARRAY_N);

            foreach ($rows as $row) {
                // Map row values and sanitize them
                $values = array_map(function ($value) use ($wpdb) {
                    if (is_null($value)) {
                        return 'NULL';
                    } elseif (is_float($value)) {
                        return $wpdb->prepare('%f', $value);
                    } else {
                        return $wpdb->prepare('%s', $value);
                    }
                }, array_values($row));

                // Write the INSERT statement into the file
                $sql = 'INSERT INTO `' . $table[0] . '` VALUES(' . implode(',', $values) . ");\n";
                fwrite($file_handle, $sql);
            }
        }

        // Close the file handle
        fclose($file_handle);

        // Compress the .sql file into a .zip file
        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE) === true) {
            $zip->addFile($sql_file, basename($sql_file)); // Add the SQL file to the zip
            $zip->close();
            unlink($sql_file); // Delete the .sql file after zipping
        } else {
            return 'Could not create zip file';
        }

        // Return the path to the zip file
        return $zip_file;
    }
}
