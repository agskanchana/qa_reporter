<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
// Check if user is admin
checkPermission(['admin']);
$user_role = getUserRole();

$page_title = "Create New Project";
$is_edit_mode = false;
$project = null;

// Get all webmasters
$webmasters_query = "SELECT id, username FROM users WHERE role = 'webmaster'";
$webmasters = $conn->query($webmasters_query);

// Check if we're in edit mode
if (isset($_GET['id'])) {
    $is_edit_mode = true;
    $project_id = (int)$_GET['id'];
    $page_title = "Edit Project";

    // Get project details
    $query = "SELECT * FROM projects WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $project = $stmt->get_result()->fetch_assoc();

    if (!$project) {
        $_SESSION['error'] = "Project not found";
        header("Location: projects.php");
        exit();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_name = $conn->real_escape_string($_POST['project_name']);
    $webmaster_id = (int)$_POST['webmaster_id'];
    $project_deadline = $conn->real_escape_string($_POST['project_deadline']);
    $wp_conversion_deadline = $conn->real_escape_string($_POST['wp_conversion_deadline']);
    $gp_link = $conn->real_escape_string($_POST['gp_link']);
    $ticket_link = $conn->real_escape_string($_POST['ticket_link']);
    $test_site_link = isset($_POST['test_site_link']) ? $conn->real_escape_string($_POST['test_site_link']) : '';
    $live_site_link = isset($_POST['live_site_link']) ? $conn->real_escape_string($_POST['live_site_link']) : '';
    $admin_notes = $_POST['admin_notes']; // Do NOT use real_escape_string on rich text
    $webmaster_notes = $_POST['webmaster_notes']; // Do NOT use real_escape_string on rich text

    if ($is_edit_mode) {
        // Update existing project
        $project_id = (int)$_POST['project_id'];

        $query = "UPDATE projects SET
                  name = ?,
                  webmaster_id = ?,
                  project_deadline = ?,
                  wp_conversion_deadline = ?,
                  gp_link = ?,
                  ticket_link = ?,
                  test_site_link = ?,
                  live_site_link = ?,
                  admin_notes = ?,
                  webmaster_notes = ?
                  WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sissssssssi", $project_name, $webmaster_id, $project_deadline,
                         $wp_conversion_deadline, $gp_link, $ticket_link,
                         $test_site_link, $live_site_link, $admin_notes,
                         $webmaster_notes, $project_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Project updated successfully!";
            header("Location: projects.php");
            exit();
        } else {
            $_SESSION['error'] = "Error updating project: " . $stmt->error;
        }
    } else {
        // Create new project
        // Check if project name already exists
        $check_query = "SELECT COUNT(*) as count FROM projects WHERE name = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $project_name);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $count = $result->fetch_assoc()['count'];

        if ($count > 0) {
            $_SESSION['error'] = "A project with this name already exists!";
        } else {
            $conn->begin_transaction();
            try {
                // Insert project with new fields
                $query = "INSERT INTO projects (name, webmaster_id, current_status, project_deadline,
                          wp_conversion_deadline, gp_link, ticket_link, test_site_link, live_site_link,
                          admin_notes, webmaster_notes)
                          VALUES (?, ?, 'wp_conversion', ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sissssssss", $project_name, $webmaster_id, $project_deadline,
                                 $wp_conversion_deadline, $gp_link, $ticket_link, $test_site_link,
                                 $live_site_link, $admin_notes, $webmaster_notes);
                $stmt->execute();

                $project_id = $conn->insert_id;

                // Insert only non-archived checklist items for new projects
                $query = "INSERT INTO project_checklist_status (project_id, checklist_item_id, status)
                        SELECT ?, id, 'idle'
                        FROM checklist_items
                        WHERE is_archived = 0";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("i", $project_id);
                $stmt->execute();

                $conn->commit();
                $_SESSION['success'] = "Project created successfully!";
                header("Location: projects.php");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Error creating project: " . $e->getMessage();
            }
        }
    }
}

// Function to calculate business days
function addBusinessDays($date, $days) {
    $result = clone $date;
    $addedDays = 0;

    while ($addedDays < $days) {
        $result->modify('+1 day');
        // Skip weekends (0 = Sunday, 6 = Saturday)
        if ($result->format('N') < 6) { // 1 (Monday) to 5 (Friday)
            $addedDays++;
        }
    }

    return $result;
}

// Set default dates for new projects
$today = new DateTime();
$wp_deadline = addBusinessDays($today, 7);
$project_deadline = addBusinessDays($today, 18);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - QA Reporter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Quill.js CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        .quill-editor {
            height: 300px;
            margin-bottom: 15px;
        }
        .quill-content {
            display: none;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h4 class="mb-0">
                    <i class="bi bi-<?php echo $is_edit_mode ? 'pencil-square' : 'plus-circle'; ?> text-primary me-2"></i>
                    <?php echo $page_title; ?>
                </h4>
            </div>
            <div class="card-body">
                <form method="POST" action="" id="projectForm">
                    <?php if ($is_edit_mode): ?>
                    <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="project_name" class="form-label">Project Name<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="project_name" name="project_name"
                                   value="<?php echo $is_edit_mode ? htmlspecialchars($project['name']) : ''; ?>" required>
                            <div id="projectNameFeedback" class="invalid-feedback"></div>
                        </div>

                        <div class="col-md-6">
                            <label for="webmaster_id" class="form-label">Assign Webmaster<span class="text-danger">*</span></label>
                            <select class="form-select" id="webmaster_id" name="webmaster_id" required>
                                <option value="">Select Webmaster</option>
                                <?php while ($webmaster = $webmasters->fetch_assoc()): ?>
                                    <option value="<?php echo $webmaster['id']; ?>"
                                            <?php echo ($is_edit_mode && $project['webmaster_id'] == $webmaster['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($webmaster['username']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="wp_conversion_deadline" class="form-label">WP Conversion Deadline<span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="wp_conversion_deadline" name="wp_conversion_deadline"
                                   value="<?php echo $is_edit_mode ? $project['wp_conversion_deadline'] : $wp_deadline->format('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="project_deadline" class="form-label">Project Deadline<span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="project_deadline" name="project_deadline"
                                   value="<?php echo $is_edit_mode ? $project['project_deadline'] : $project_deadline->format('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="gp_link" class="form-label">GP Link (Google Spreadsheet)<span class="text-danger">*</span></label>
                            <input type="url" class="form-control" id="gp_link" name="gp_link"
                                   value="<?php echo $is_edit_mode ? htmlspecialchars($project['gp_link']) : ''; ?>"
                                   placeholder="https://docs.google.com/spreadsheets/..." required>
                        </div>

                        <div class="col-md-6">
                            <label for="ticket_link" class="form-label">Ticket Link<span class="text-danger">*</span></label>
                            <input type="url" class="form-control" id="ticket_link" name="ticket_link"
                                   value="<?php echo $is_edit_mode ? htmlspecialchars($project['ticket_link']) : ''; ?>"
                                   placeholder="https://..." required>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="test_site_link" class="form-label">Test Site Link <span class="text-muted">(optional)</span></label>
                            <input type="url" class="form-control" id="test_site_link" name="test_site_link"
                                   value="<?php echo $is_edit_mode ? htmlspecialchars($project['test_site_link']) : ''; ?>"
                                   placeholder="https://...">
                        </div>

                        <div class="col-md-6">
                            <label for="live_site_link" class="form-label">Live Site Link <span class="text-muted">(optional)</span></label>
                            <input type="url" class="form-control" id="live_site_link" name="live_site_link"
                                   value="<?php echo $is_edit_mode ? htmlspecialchars($project['live_site_link']) : ''; ?>"
                                   placeholder="https://...">
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label for="admin_notes" class="form-label">Notes by Admin</label>
                            <div id="admin_notes_editor" class="quill-editor"></div>
                            <textarea class="quill-content" id="admin_notes" name="admin_notes"><?php
                                echo $is_edit_mode ? $project['admin_notes'] : '';
                            ?></textarea>
                        </div>

                        <div class="col-md-6">
                            <label for="webmaster_notes" class="form-label">Notes by Webmaster</label>
                            <div id="webmaster_notes_editor" class="quill-editor"></div>
                            <textarea class="quill-content" id="webmaster_notes" name="webmaster_notes"
                                      <?php echo $user_role !== 'admin' ? 'readonly' : ''; ?>><?php
                                echo $is_edit_mode ? $project['webmaster_notes'] : '';
                            ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="projects.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back to Projects
                        </a>
                        <button type="submit" class="btn btn-primary px-4" id="submitBtn">
                            <?php echo $is_edit_mode ? 'Save Changes' : 'Create Project'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Quill.js JavaScript -->
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Quill editors
        const adminEditor = new Quill('#admin_notes_editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    [{'color': []}, {'background': []}],
                    [{'list': 'ordered'}, {'list': 'bullet'}],
                    [{'align': []}],
                    ['link'],
                    ['clean']
                ]
            },
            placeholder: 'Add administrator notes here...'
        });

        const webmasterEditor = new Quill('#webmaster_notes_editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    [{'color': []}, {'background': []}],
                    [{'list': 'ordered'}, {'list': 'bullet'}],
                    [{'align': []}],
                    ['link'],
                    ['clean']
                ]
            },
            placeholder: 'Add webmaster notes here...'
        });

        // Set initial content
        const adminNotesContent = document.getElementById('admin_notes').value;
        if (adminNotesContent) {
            adminEditor.root.innerHTML = adminNotesContent;
        }

        const webmasterNotesContent = document.getElementById('webmaster_notes').value;
        if (webmasterNotesContent) {
            webmasterEditor.root.innerHTML = webmasterNotesContent;
        }

        <?php if ($user_role !== 'admin'): ?>
        // Make webmaster editor read-only for non-admin users
        webmasterEditor.disable();
        <?php endif; ?>

        // Update hidden fields with editor content before form submission
        document.getElementById('projectForm').addEventListener('submit', function() {
            document.getElementById('admin_notes').value = adminEditor.root.innerHTML;
            document.getElementById('webmaster_notes').value = webmasterEditor.root.innerHTML;
        });

        // Project name validation for new projects
        <?php if (!$is_edit_mode): ?>
        const projectNameInput = document.getElementById('project_name');
        const submitBtn = document.getElementById('submitBtn');
        let timeoutId;

        if (projectNameInput) {
            projectNameInput.addEventListener('input', function() {
                clearTimeout(timeoutId);
                const projectName = this.value;
                const feedbackElement = document.getElementById('projectNameFeedback');

                // Remove any existing feedback
                this.classList.remove('is-invalid', 'is-valid');

                if (projectName.length > 0) {
                    timeoutId = setTimeout(() => {
                        fetch('check_project_name.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'project_name=' + encodeURIComponent(projectName)
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.exists) {
                                projectNameInput.classList.add('is-invalid');
                                submitBtn.disabled = true;
                                feedbackElement.textContent = 'This project name already exists!';
                            } else {
                                projectNameInput.classList.add('is-valid');
                                submitBtn.disabled = false;
                            }
                        });
                    }, 500);
                }
            });
        }
        <?php endif; ?>
    });
    </script>
</body>
</html>