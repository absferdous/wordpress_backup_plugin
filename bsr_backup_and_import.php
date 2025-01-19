<?php
/*
Plugin Name: bsr_backup_and_import
Description: A custom backup plugin for WordPress.
Version: 1.0
Author:Ferdous Ahmed
*/


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
require_once plugin_dir_path(__FILE__).'includes/class-database-backup.php';
require_once plugin_dir_path(__FILE__).'includes/class-file-backup.php';

// Add the side menu
function bsr_add_side_menu() {
    
    add_menu_page(
        'BSR Home Page',        // Page title
        'BSR Sidemenu',         // Menu title
        'manage_options',       // Capability
        'bsr-sidemenu-page',    // Menu slug
        'bsr_sidemenu_page_html', // Callback function
        'dashicons-admin-generic', // Icon (optional)
        6                       // Position in the menu
    );
}
add_action('admin_menu', 'bsr_add_side_menu');


//admin page

function bsr_sidemenu_page_html() {
    ?>
    
    <div class="wrap">
        <div class="container">
 <!-- Backup Section -->
            <h1 class="large-text">BSR backup plugin</h1>
        </div>
        <div class="nav-tab-wrapper">
        <a href="#backup-tab" class="nav-tab nav-tab-active">Backup</a>
        <a href="#restore-tab" class="nav-tab">Restore</a>
    </div>
    <div id="backup-tab" class="tab-content mt-6">
        <button id="backup-button" class="button button-primary">Run Backup</button>
        <div id="backup-log"></div>
    </div>
        <!-- <button class="button button-primary" id="backup-button">Run Backup</button>
        <div id="backup-log" class="large-text"></div> -->
         <!-- Back-up count -->
        <div id="backup-count" >
            
<?php echo bsr_backupcount(); ?> 
        </div>

         <!-- Restore Section -->
         <h2>Restore</h2>
         
        <form id="restore-form" method="post" enctype="multipart/form-data">
            <label for="backup-file">Upload Backup File (.zip):</label>
            <input type="file" name="backup-file" id="backup-file" accept=".zip" required>

            <button type="submit" class="button button-secondary">Restore</button>

            <p>Select what to restore:</p>
    <input type="checkbox" name="restore-database" id="restore-database" checked>
    <label for="restore-database">Restore Database</label><br>
    <input type="checkbox" name="restore-files" id="restore-files" checked>
    <label for="restore-files">Restore Files</label><br>
    
    <button type="submit" class="button button-secondary">Restore</button>
        </form>

        <div id="restore-log"></div>

        <script>
            console.log('clicked');
            // Backup Button
            document.getElementById('backup-button').addEventListener('click', function () {
                fetch(ajaxurl + '?action=my_run_backup')
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('backup-log').textContent = data.message;
                    })
                    .catch(error => {
                        document.getElementById('backup-log').textContent = 'An error occurred: ' + error;
                    });
            });

              // Restore Form Submission
            document.getElementById('restore-form').addEventListener('submit', function (e) {
                e.preventDefault();
                const formData = new FormData(this);

                fetch(ajaxurl + '?action=my_run_restore', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('restore-log').textContent = data.message;
                })
                .catch(error => {
                    document.getElementById('restore-log').textContent = 'An error occurred: ' + error;
                });
            });
        </script>
         <style>
            .error{
              color: red;
            }
         </style>
    </div>
    <?php
}


// Backup Action
add_action('wp_ajax_my_run_backup', function () {
    if(!current_user_can('manage_options')){
        wp_die('unauthorized action');
    }
    

	$db_backup = new bsr_Database_backup();
	// $file_backup = new bsr_File_Backup();
   
	$db_file = $db_backup->backup_database();
	// $zip_file = $file_backup->file_backup();

      // Check for backup errors.
      $errors = [];
      if (is_string($db_file) && strpos($db_file, 'Could not') === 0 ) { // Check is string and if it starts with 'Could not'
          $errors[] = 'Database backup error: ' . $db_file;
      }
    //    if (is_string($zip_file) && strpos($zip_file, 'Could not') === 0 ) {
    //        $errors[] = 'File backup error: ' . $zip_file;
    //   }

      if (!empty($errors)){
        wp_send_json([
            'success' => false,
            'message' => 'Backup Failed:' . implode(' | ', $errors)
        ]);
        return;
     }

	wp_send_json([  'success' => true,
        'message' => "Backup completed! ",
        'db_file_url' =>  $db_file,
        // 'zip_file_url' => admin_url('admin-ajax.php?action=download_backup_file&file=' . urlencode($zip_file)),
    ]);
});




//Restore functionality
add_action('wp_ajax_my_run_restore', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'You do not have permission to restore backups.']);
        return;
    }

    // Check for uploaded file
    if (empty($_FILES['backup-file']) || $_FILES['backup-file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json_error(['message' => 'No valid backup file uploaded.']);
        return;
    }

    // Validate file type
    $uploaded_file = $_FILES['backup-file'];
    if (pathinfo($uploaded_file['name'], PATHINFO_EXTENSION) !== 'zip') {
        wp_send_json_error(['message' => 'Only .zip files are allowed.']);
        return;
    }

    // Move uploaded file to a temporary directory
    $backup_dir = wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . 'my_backups_new';
    $temp_file = $backup_dir . DIRECTORY_SEPARATOR . basename($uploaded_file['name']);
    move_uploaded_file($uploaded_file['tmp_name'], $temp_file);

    // Extract the zip file
    $zip = new ZipArchive();
    if ($zip->open($temp_file) === true) {
        $extract_to = $backup_dir . '/restore_temp';
        $zip->extractTo($extract_to);
        $zip->close();
    } else {
        wp_send_json_error(['message' => 'Failed to extract the zip file.']);
        return;
    }

    // ** Partial Restore Logic Here **
    $restore_database = isset($_POST['restore-database']);
    $restore_files = isset($_POST['restore-files']);

    $db_restore_file = $extract_to . '/db_backup.sql';
    $files_dir = $extract_to . '/files';

    // Restore database if requested
    if ($restore_database && file_exists($db_restore_file)) {
        global $wpdb;
        $queries = file_get_contents($db_restore_file);
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0;'); // Disable foreign key checks
        $wpdb->query($queries);
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1;'); // Re-enable foreign key checks
    }

    // Restore files if requested
    if ($restore_files && is_dir($files_dir)) {
        recurse_copy($files_dir, WP_CONTENT_DIR);
    }

    // Cleanup
    unlink($temp_file);
    array_map('unlink', glob("$extract_to/*"));
    rmdir($extract_to);

    wp_send_json(['message' => 'Restore completed successfully.']);
});

// Helper function to copy files recursively
function recurse_copy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            if (is_dir($src . '/' . $file)) {
                recurse_copy($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }
    closedir($dir);
}


// end of restore functionality



















//backup count

// Function to count backup files
function bsr_backupcount() {
    $upload_dir = wp_upload_dir();
    $db_backup_dir = $upload_dir['basedir'] . '/my_backups_new';

    if (!is_dir($db_backup_dir)) {
        return "<div class='error'>Backup directories not found.</div>";
    }

    $db_backup_count = count(glob("$db_backup_dir/db_backup_*.zip"));
    $file_backup_count = count(glob("$db_backup_dir/file_backup_*.zip"));

    return "
        <div class='wrap'>
            <h4>Backup Counts</h4>
            <p>Database Backups: <strong>$db_backup_count</strong></p>
            <p>File Backups: <strong>$file_backup_count</strong></p>
        </div>
    ";
}


//filebackup


add_action('wp_ajax_my_run_backup', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized action');
    }

    $db_backup = new bsr_Database_backup();
    $file_backup = new bsr_File_Backup();

    $db_file = $db_backup->backup_database();
    $file_zip = $file_backup->file_backup();

    // Check for backup errors
    $errors = [];
    if (is_string($db_file) && strpos($db_file, 'Could not') === 0) {
        $errors[] = 'Database backup error: ' . $db_file;
    }
    if (is_string($file_zip) && strpos($file_zip, 'Could not') === 0) {
        $errors[] = 'File backup error: ' . $file_zip;
    }

    if (!empty($errors)) {
        wp_send_json([
            'success' => false,
            'message' => 'Backup Failed: ' . implode(' | ', $errors),
        ]);
        return;
    }

    wp_send_json([
        'success' => true,
        'message' => 'Backup completed!',
        'db_file_url' => $db_file,
        'file_zip_url' => $file_zip,
    ]);
});


