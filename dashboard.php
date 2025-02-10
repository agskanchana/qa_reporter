<?php

require_once 'includes/config.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

$user_role = getUserRole();
$user_id = $_SESSION['user_id'];

// Handle QA Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($user_role, ['admin', 'qa_manager'])) {
    if (isset($_POST['assign_qa']) && isset($_POST['project_id']) && isset($_POST['qa_user_id'])) {
        $project_id = (int)$_POST['project_id'];
        $qa_user_id = (int)$_POST['qa_user_id'];

        // Check if project is already assigned
        $check_stmt = $conn->prepare("SELECT id FROM qa_assignments WHERE project_id = ?");
        $check_stmt->bind_param("i", $project_id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result();

        if ($existing->num_rows > 0) {
            // Update existing assignment
            $stmt = $conn->prepare("UPDATE qa_assignments SET qa_user_id = ?, assigned_by = ?, assigned_at = NOW() WHERE project_id = ?");
            $stmt->bind_param("iii", $qa_user_id, $user_id, $project_id);
        } else {
            // Create new assignment
            $stmt = $conn->prepare("INSERT INTO qa_assignments (project_id, qa_user_id, assigned_by) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $project_id, $qa_user_id, $user_id);
        }
        $stmt->execute();

        header("Location: dashboard.php");
        exit();
    }
}

// Get all QA reporters for assignment dropdown
$qa_reporters = [];
if (in_array($user_role, ['admin', 'qa_manager'])) {
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE role = 'qa_reporter'");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $qa_reporters[] = $row;
    }
}

// Get projects based on user role
$projects = [];
$unassigned_projects = [];

if ($user_role === 'qa_reporter') {
    // Get only assigned projects for QA reporter
    $query = "SELECT p.*, u.username as webmaster_name, qa.id as assignment_id
              FROM projects p
              LEFT JOIN users u ON p.webmaster_id = u.id
              INNER JOIN qa_assignments qa ON p.id = qa.project_id
              WHERE qa.qa_user_id = ?
              ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
elseif (in_array($user_role, ['admin', 'qa_manager'])) {
    // Get all projects and separate assigned/unassigned
    $query = "SELECT p.*, u.username as webmaster_name,
              qa.id as assignment_id, qa_user.username as assigned_qa_username
              FROM projects p
              LEFT JOIN users u ON p.webmaster_id = u.id
              LEFT JOIN qa_assignments qa ON p.id = qa.project_id
              LEFT JOIN users qa_user ON qa.qa_user_id = qa_user.id
              ORDER BY p.created_at DESC";
    $result = $conn->query($query);

    while ($row = $result->fetch_assoc()) {
        if ($row['assignment_id'] === null) {
            $unassigned_projects[] = $row;
        } else {
            $projects[] = $row;
        }
    }
}
else {
    // For webmaster, get their projects
    $query = "SELECT p.*, u.username as webmaster_name,
              qa.id as assignment_id, qa_user.username as assigned_qa_username
              FROM projects p
              LEFT JOIN users u ON p.webmaster_id = u.id
              LEFT JOIN qa_assignments qa ON p.id = qa.project_id
              LEFT JOIN users qa_user ON qa.qa_user_id = qa_user.id
              WHERE p.webmaster_id = ?
              ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

require_once 'includes/header.php';
?>



    <div class="container-fluid">
        <div class="row">


            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                </div>

                <?php if (in_array($user_role, ['admin', 'qa_manager']) && !empty($unassigned_projects)): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-warning">
                            <h4>Unassigned Projects</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Project Name</th>
                                            <th>Status</th>
                                            <th>Webmaster</th>
                                            <th>Created At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($unassigned_projects as $project): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($project['name']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?php echo ucfirst(str_replace('_', ' ', $project['current_status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($project['webmaster_name']); ?></td>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                                <td>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                                        <select name="qa_user_id" class="form-select form-select-sm d-inline-block w-auto" required>
                                                            <option value="">Assign QA</option>
                                                            <?php foreach ($qa_reporters as $qa): ?>
                                                                <option value="<?php echo $qa['id']; ?>">
                                                                    <?php echo htmlspecialchars($qa['username']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" name="assign_qa" class="btn btn-primary btn-sm">
                                                            Assign
                                                        </button>
                                                    </form>
                                                    <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                                       class="btn btn-info btn-sm">View</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h4><?php echo in_array($user_role, ['admin', 'qa_manager']) ? 'Assigned Projects' : 'Projects'; ?></h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Project Name</th>
                                        <th>Status</th>
                                        <th>Webmaster</th>
                                        <?php if (in_array($user_role, ['admin', 'qa_manager'])): ?>
                                            <th>Assigned QA</th>
                                        <?php endif; ?>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($project['name']); ?></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?php echo ucfirst(str_replace('_', ' ', $project['current_status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($project['webmaster_name']); ?></td>
                                            <?php if (in_array($user_role, ['admin', 'qa_manager'])): ?>
                                                <td><?php echo htmlspecialchars($project['assigned_qa_username'] ?? 'None'); ?></td>
                                            <?php endif; ?>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($project['created_at'])); ?></td>
                                            <td>
                                                <a href="view_project.php?id=<?php echo $project['id']; ?>"
                                                   class="btn btn-info btn-sm">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>