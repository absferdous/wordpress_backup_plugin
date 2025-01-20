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
        
    <div id="backup-tab" class="tab-content mt-6">
        <button id="backup-button" class="button button-primary">Run Backup</button>
        <div id="backup-log"></div>
        <div id="backup-progress" style="display: none; margin-top: 10px;">
                <progress id="progress-bar" value="0" max="100" style="width: 100%;"></progress>
                <span id="progress-percentage">0%</span>
            </div>
    </div>
       
         <!-- Back-up count -->
        <div id="backup-count" >
            

            <?php echo bsr_backupcount(); ?>
        </div>

         
         
        

<script>
            // Backup Button
            document.getElementById('backup-button').addEventListener('click', function () {
    const logElement = document.getElementById('backup-log');
    const progressBar = document.getElementById('progress-bar');
    const progressPercentage = document.getElementById('progress-percentage');
    const progressContainer = document.getElementById('backup-progress');

    logElement.textContent = 'Initializing backup...';
    progressContainer.style.display = 'block';
    progressBar.value = 0;
    progressPercentage.textContent = '0%';

    const interval = setInterval(() => {
        fetch(ajaxurl + '?action=my_run_backup')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    progressBar.value = 100;
                    progressPercentage.textContent = '100%';
                    logElement.textContent = 'Backup completed successfully!';
                    clearInterval(interval);
                } else {
                    // Update progress dynamically if the server returns progress data
                    if (data.progress) {
                        progressBar.value = data.progress;
                        progressPercentage.textContent = data.progress + '%';
                    }
                    if (data.message) {
                        logElement.textContent = data.message;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                logElement.textContent = 'An error occurred: ' + error;
                clearInterval(interval);
            });
    }, 1000);
});

</script>
        
    <?php
}



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

    ini_set('memory_limit', '1024M'); // Adjust as needed
    set_time_limit(600); // Extend execution time

    $db_backup = new bsr_Database_backup();
    $file_backup = new bsr_File_Backup();

    error_log("Starting database backup...");
    $db_file = $db_backup->backup_database();
    error_log("Database backup completed: $db_file");

    error_log("Starting file backup...");
    $file_zip = $file_backup->file_backup();
    error_log("File backup completed: $file_zip");

    $errors = [];
    if (is_string($db_file) && strpos($db_file, 'Could not') === 0) {
        $errors[] = 'Database backup error: ' . $db_file;
    }
    if (is_string($file_zip) && strpos($file_zip, 'Could not') === 0) {
        $errors[] = 'File backup error: ' . $file_zip;
    }

    if (!empty($errors)) {
        error_log("Backup errors: " . implode(' | ', $errors));
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



