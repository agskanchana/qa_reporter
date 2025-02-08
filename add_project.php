<?php
// add_project.php
require_once 'config.php';

// Check if user is admin
checkPermission(['admin']);

// Get all webmasters
$webmasters_query = "SELECT id, username FROM users WHERE role = 'webmaster'";
$webmasters = $conn->query($webmasters_query);

// Get default checklist items
$checklist_query = "SELECT * FROM checklist_items ORDER BY stage, id";
$checklist_items = $conn->query($checklist_query);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $project_name = $conn->real_escape_string($_POST['project_name']);
    $webmaster_id = (int)$_POST['webmaster_id'];

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert project
        $query = "INSERT INTO projects (name, webmaster_id, current_status) VALUES (?, ?, 'wp_conversion')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $project_name, $webmaster_id);
        $stmt->execute();

        $project_id = $conn->insert_id;

        // Insert default checklist items for the project
        $query = "INSERT INTO project_checklist_status (project_id, checklist_item_id, status)
                 SELECT ?, id, 'idle' FROM checklist_items";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $project_id);
        $stmt->execute();

        $conn->commit();
        $success = "Project created successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error creating project: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Project - QA Reporter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">QA Reporter</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <a class="nav-link" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h2>Add New Project</h2>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
                <a href="dashboard.php" class="alert-link">Return to Dashboard</a>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="project_name" class="form-label">Project Name</label>
                        <input type="text" class="form-control" id="project_name" name="project_name" required>
                    </div>

                    <div class="mb-3">
                        <label for="webmaster_id" class="form-label">Assign Webmaster</label>
                        <select class="form-select" id="webmaster_id" name="webmaster_id" required>
                            <option value="">Select Webmaster</option>
                            <?php while ($webmaster = $webmasters->fetch_assoc()): ?>
                                <option value="<?php echo $webmaster['id']; ?>">
                                    <?php echo htmlspecialchars($webmaster['username']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Create Project</button>
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>