<?php
// Get the user ID from the session
$user_id = $_SESSION['user_id'] ?? 0;

// Check if this is a direct AJAX request to this file
$is_direct_ajax_request = (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_auto_assign']) &&
    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
);

// If it's a direct AJAX request, we need to include required files
if ($is_direct_ajax_request) {
    // Define the path to config relative to this file
    $config_path = dirname(dirname(dirname(__FILE__))) . '/config.php';
    require_once $config_path;

    // Get the user ID from the session if not already set
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $user_id = $_SESSION['user_id'] ?? 0;
}

// Get current auto-assign settings
$wp_conversion_auto_assign = false;
$golive_auto_assign = false;

// Check if auto_assign_to_admin table exists and get settings
$table_check = $conn->query("SHOW TABLES LIKE 'auto_assign_to_admin'");
if ($table_check && $table_check->num_rows > 0) {
    $settings_query = "SELECT setting_key, is_enabled FROM auto_assign_to_admin";
    $settings_result = $conn->query($settings_query);

    if ($settings_result) {
        while ($row = $settings_result->fetch_assoc()) {
            if ($row['setting_key'] == 'wp_conversion') {
                $wp_conversion_auto_assign = (bool)$row['is_enabled'];
            } elseif ($row['setting_key'] == 'golive') {
                $golive_auto_assign = (bool)$row['is_enabled'];
            }
        }
    }
}

// Handle toggle updates via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_auto_assign'])) {
    $setting_key = $_POST['setting_key'] ?? '';
    $setting_value = isset($_POST['setting_value']) ? (int)$_POST['setting_value'] : 0;

    // Map the setting key from the form to the database key
    $db_setting_key = '';
    if ($setting_key === 'auto_assign_wp_conversion') {
        $db_setting_key = 'wp_conversion';
    } elseif ($setting_key === 'auto_assign_golive') {
        $db_setting_key = 'golive';
    }

    // Validate setting key
    if (!empty($db_setting_key)) {
        try {
            $conn->begin_transaction();

            // Check if auto_assign_to_admin table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'auto_assign_to_admin'");

            if ($table_check && $table_check->num_rows === 0) {
                // Create table if it doesn't exist
                $create_table_sql = "CREATE TABLE auto_assign_to_admin (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_key VARCHAR(50) NOT NULL UNIQUE,
                    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";
                $conn->query($create_table_sql);

                // Insert default settings
                $conn->query("INSERT INTO auto_assign_to_admin (setting_key, is_enabled) VALUES ('wp_conversion', 0), ('golive', 0)");
            }

            // Update setting
            $update_stmt = $conn->prepare("INSERT INTO auto_assign_to_admin (setting_key, is_enabled)
                                        VALUES (?, ?)
                                        ON DUPLICATE KEY UPDATE is_enabled = ?");
            $update_stmt->bind_param("sii", $db_setting_key, $setting_value, $setting_value);
            $update_stmt->execute();

            // Update local variable for immediate display effect
            if ($db_setting_key === 'wp_conversion') {
                $wp_conversion_auto_assign = (bool)$setting_value;
            } elseif ($db_setting_key === 'golive') {
                $golive_auto_assign = (bool)$setting_value;
            }

            // Skip logging to activity_log if user isn't logged in
            if ($user_id > 0) {
                // Check if activity_log table exists before logging
                $activity_table_check = $conn->query("SHOW TABLES LIKE 'activity_log'");
                if ($activity_table_check && $activity_table_check->num_rows === 0) {
                    // Create activity_log table if it doesn't exist
                    $create_activity_table_sql = "CREATE TABLE activity_log (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        action TEXT NOT NULL,
                        ip_address VARCHAR(45),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )";
                    $conn->query($create_activity_table_sql);
                }

                // Record action in activity log
                $action = "Updated auto-assign setting: $setting_key to " . ($setting_value ? 'enabled' : 'disabled');
                $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, ip_address, created_at)
                                        VALUES (?, ?, ?, NOW())");
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bind_param("iss", $user_id, $action, $ip);
                $log_stmt->execute();
            }

            $conn->commit();

            // Send success response if this is an AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);

                // If it's a direct AJAX request to this file, exit after response
                if ($is_direct_ajax_request) {
                    exit;
                }
            }

        } catch (Exception $e) {
            // Roll back transaction on error
            $conn->rollback();

            // Send error response for AJAX request
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);

                // If it's a direct AJAX request to this file, exit after response
                if ($is_direct_ajax_request) {
                    exit;
                }
            }
        }
    }
}

// If this is a direct AJAX request, we've already sent the response and should exit
if ($is_direct_ajax_request) {
    exit;
}
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">
            <i class="bi bi-gear-fill me-2 text-primary"></i>
            Auto Assign to Admin
        </h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">
            Enable auto-assignment of QA tasks to the admin account for specific project stages. When enabled,
            the admin will automatically be assigned as the QA reviewer when projects reach these stages.
        </p>

        <div class="list-group">
            <div class="list-group-item border-0 px-0 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">WP Conversion</h6>
                    <p class="text-muted small mb-0">
                        Auto-assign WP conversion QA tasks to admin
                    </p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input auto-assign-toggle" type="checkbox" role="switch"
                           id="wp_conversion_toggle"
                           data-setting-key="auto_assign_wp_conversion"
                           <?php echo $wp_conversion_auto_assign ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="wp_conversion_toggle"></label>
                </div>
            </div>

            <div class="list-group-item border-0 px-0 d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-1">Go-Live</h6>
                    <p class="text-muted small mb-0">
                        Auto-assign Go-Live QA tasks to admin
                    </p>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input auto-assign-toggle" type="checkbox" role="switch"
                           id="golive_toggle"
                           data-setting-key="auto_assign_golive"
                           <?php echo $golive_auto_assign ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="golive_toggle"></label>
                </div>
            </div>
        </div>

        <div class="mt-3 text-center d-none" id="settings-saved-alert">
            <div class="alert alert-success py-2 px-3 d-inline-block">
                <i class="bi bi-check-circle me-1"></i>
                Setting saved successfully
            </div>
        </div>

        <div class="mt-3 text-center d-none" id="settings-error-alert">
            <div class="alert alert-danger py-2 px-3 d-inline-block">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <span id="error-message">Error saving setting</span>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle toggle switches via AJAX
    const toggleSwitches = document.querySelectorAll('.auto-assign-toggle');

    toggleSwitches.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const settingKey = this.dataset.settingKey;
            const settingValue = this.checked ? 1 : 0;

            // Show spinner or some indication
            this.disabled = true;

            // Send AJAX request directly to the auto_assign_settings.php file
            fetch('<?php echo BASE_URL; ?>/includes/admin/widgets/auto_assign_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `update_auto_assign=1&setting_key=${settingKey}&setting_value=${settingValue}`
            })
            .then(response => {
                // Check if the response is valid JSON
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.indexOf('application/json') !== -1) {
                    return response.json();
                } else {
                    // For debugging
                    return response.text().then(text => {
                        console.error('Invalid response format:', text);
                        throw new Error('Server returned invalid response format');
                    });
                }
            })
            .then(data => {
                this.disabled = false;

                if (data.success) {
                    // Show success message briefly
                    const alertEl = document.getElementById('settings-saved-alert');
                    alertEl.classList.remove('d-none');
                    setTimeout(() => {
                        alertEl.classList.add('d-none');
                    }, 2000);
                } else {
                    // Show error message
                    const errorEl = document.getElementById('settings-error-alert');
                    const errorMsgEl = document.getElementById('error-message');
                    if (data.error) {
                        errorMsgEl.textContent = data.error;
                    }
                    errorEl.classList.remove('d-none');
                    setTimeout(() => {
                        errorEl.classList.add('d-none');
                    }, 4000);

                    // Revert toggle
                    this.checked = !this.checked;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.disabled = false;

                // Show error message
                const errorEl = document.getElementById('settings-error-alert');
                const errorMsgEl = document.getElementById('error-message');
                errorMsgEl.textContent = 'Connection error. Please try again.';
                errorEl.classList.remove('d-none');
                setTimeout(() => {
                    errorEl.classList.add('d-none');
                }, 4000);

                // Revert toggle if there was an error
                this.checked = !this.checked;
            });
        });
    });
});
</script>