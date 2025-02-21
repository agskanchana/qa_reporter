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
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE role IN ('qa_reporter', 'qa_manager', 'admin') ORDER BY username");
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
              qa.id as assignment_id, IFNULL(qa_user.username, 'None') as assigned_qa_username
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
              qa.id as assignment_id, COALESCE(qa_user.username, 'None') as assigned_qa_username
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

// Replace the webmaster projects query with this
if ($user_role === 'admin') {
    $query = "SELECT
                u.id as webmaster_id,
                COALESCE(u.username, 'Deleted User') as webmaster_name,
                p.id as project_id,
                p.name as project_name,
                p.current_status
              FROM users u
              LEFT JOIN projects p ON u.id = p.webmaster_id
                AND p.current_status != 'completed'
              WHERE u.role = 'webmaster'
              ORDER BY u.username, p.created_at DESC";

    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $webmaster_id = $row['webmaster_id'];
        if (!isset($webmaster_projects[$webmaster_id])) {
            $webmaster_projects[$webmaster_id] = [
                'name' => $row['webmaster_name'],
                'projects' => []
            ];
        }
        if ($row['project_id']) {
            $webmaster_projects[$webmaster_id]['projects'][] = [
                'id' => $row['project_id'],
                'name' => $row['project_name'],
                'status' => $row['current_status']
            ];
        }
    }
}

// Add after the webmaster_projects query

$qa_reporter_projects = [];
if (in_array($user_role, ['admin', 'qa_manager'])) {
    $query = "SELECT
                u.id as qa_id,
                COALESCE(u.username, 'Deleted User') as qa_name,
                p.id as project_id,
                p.name as project_name,
                p.current_status
              FROM users u
              LEFT JOIN qa_assignments qa ON u.id = qa.qa_user_id
              LEFT JOIN projects p ON qa.project_id = p.id
              WHERE (u.role = 'qa_reporter' OR u.role = 'qa_manager' OR u.role = 'admin' OR u.id IS NULL)
              AND (p.current_status != 'completed' OR p.current_status IS NULL)
              ORDER BY COALESCE(u.username, 'Deleted User'), p.created_at DESC";

    $result = $conn->query($query);
    while ($row = $result->fetch_assoc()) {
        $qa_id = $row['qa_id'];
        if (!isset($qa_reporter_projects[$qa_id])) {
            $qa_reporter_projects[$qa_id] = [
                'name' => $row['qa_name'],
                'projects' => []
            ];
        }
        if ($row['project_id']) {
            $qa_reporter_projects[$qa_id]['projects'][] = [
                'id' => $row['project_id'],
                'name' => $row['project_name'],
                'status' => $row['current_status']
            ];
        }
    }
}

// Add after existing queries, before require_once 'includes/header.php'

// Get most failing checklist items by stage
$failing_items_query = "SELECT
    ci.title,
    ci.stage,
    COUNT(pcs.id) as fail_count
FROM checklist_items ci
JOIN project_checklist_status pcs ON ci.id = pcs.checklist_item_id
WHERE pcs.status = 'failed'
    AND ci.is_archived = 0
    AND pcs.is_archived = 0
GROUP BY ci.id, ci.title, ci.stage
HAVING COUNT(pcs.id) > 0
ORDER BY ci.stage, fail_count DESC";

$failing_items_result = $conn->query($failing_items_query);
$failing_items = [];
while ($row = $failing_items_result->fetch_assoc()) {
    $failing_items[$row['stage']][] = $row;
}

require_once 'includes/header.php';
?>



    <div class="container-fluid">
        <div class="row">


            <main class="col-md-12">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    </div>
                <div class="row">

        <div class="col-md-3">
        <?php if($user_role === 'webmaster'):?>
            <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Most Failing Items</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="accordion" id="failingItemsAccordion">
                            <?php
                            $stages = ['wp_conversion' => 'WP Conversion',
                                      'page_creation' => 'Page Creation',
                                      'golive' => 'Golive'];

                            foreach ($stages as $stage_key => $stage_name):
                                $items = $failing_items[$stage_key] ?? [];
                            ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#failing<?php echo $stage_key; ?>">
                                            <?php echo $stage_name; ?>
                                            <?php if (!empty($items)): ?>
                                                <span class="badge bg-danger ms-2">
                                                    <?php echo count($items); ?>
                                                </span>
                                            <?php endif; ?>
                                        </button>
                                    </h2>
                                    <div id="failing<?php echo $stage_key; ?>"
                                         class="accordion-collapse collapse"
                                         data-bs-parent="#failingItemsAccordion">
                                        <div class="accordion-body p-2">
                                            <?php if (empty($items)): ?>
                                                <p class="text-muted mb-0">No failing items</p>
                                            <?php else: ?>
                                                <?php foreach ($items as $item): ?>
                                                    <div class="card mb-2">
                                                        <div class="card-body p-2">
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <small><?php echo htmlspecialchars($item['title']); ?></small>
                                                                <span class="badge bg-danger">
                                                                    <?php echo $item['fail_count']; ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
        <?php endif;?>
        <?php if ($user_role === 'admin'): ?>
        <div class="card mb-4">
                <div class="card-header ">
                    <h5 class="card-title mb-0">Webmaster Projects</h5>
                </div>
                <div class="card-body p-0">
                    <div class="accordion" id="webmasterAccordion">
                        <?php foreach ($webmaster_projects as $webmaster_id => $webmaster): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#webmaster<?php echo $webmaster_id; ?>">
                                        <?php echo htmlspecialchars($webmaster['name']); ?>
                                        <span class="badge bg-<?php echo count($webmaster['projects']) === 0 ? 'danger' : 'primary'; ?> ms-2">
                                            <?php echo count($webmaster['projects']); ?>
                                        </span>
                                    </button>
                                </h2>
                                <div id="webmaster<?php echo $webmaster_id; ?>"
                                     class="accordion-collapse collapse"
                                     data-bs-parent="#webmasterAccordion">
                                    <div class="accordion-body p-2">
                                        <?php if (empty($webmaster['projects'])): ?>
                                            <p class="text-muted mb-0">No active projects</p>
                                        <?php else: ?>
                                            <?php foreach ($webmaster['projects'] as $project): ?>
                                                <div class="card mb-2">
                                                    <div class="card-body p-2">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small><?php echo htmlspecialchars($project['name']); ?></small>
                                                            <span class="badge bg-<?php
                                                                echo match($project['status']) {
                                                                    'wp_conversion' => 'info',
                                                                    'page_creation' => 'warning',
                                                                    'golive' => 'primary',
                                                                    default => 'secondary'
                                                                };
                                                            ?>">
                                                                <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php
            endif;

            if (in_array($user_role, ['admin', 'qa_manager'])): ?>

   <div class="card ">
        <div class="card-header">
            <h5 class="card-title mb-0">QA Reporter Projects</h5>
        </div>
        <div class="card-body p-0">
            <div class="accordion" id="qaReporterAccordion">
                <?php foreach ($qa_reporter_projects as $qa_id => $qa): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#qa<?php echo $qa_id; ?>">
                                <?php echo htmlspecialchars($qa['name']); ?>
                                <span class="badge bg-<?php echo count($qa['projects']) === 0 ? 'danger' : 'success'; ?> ms-2">
                                    <?php echo count($qa['projects']); ?>
                                </span>
                            </button>
                        </h2>
                        <div id="qa<?php echo $qa_id; ?>"
                             class="accordion-collapse collapse"
                             data-bs-parent="#qaReporterAccordion">
                            <div class="accordion-body p-2">
                                <?php if (empty($qa['projects'])): ?>
                                    <p class="text-muted mb-0">No active projects</p>
                                <?php else: ?>
                                    <?php foreach ($qa['projects'] as $project): ?>
                                        <div class="card mb-2">
                                            <div class="card-body p-2">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small><?php echo htmlspecialchars($project['name']); ?></small>
                                                    <span class="badge bg-<?php
                                                        echo match($project['status']) {
                                                            'wp_conversion' => 'info',
                                                            'page_creation' => 'warning',
                                                            'golive' => 'primary',
                                                            default => 'secondary'
                                                        };
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
        </div>


        <main class="col-md-9">



                <?php if (in_array($user_role, ['admin', 'qa_manager']) && !empty($unassigned_projects)): ?>
                    <div class="card mb-4">
                        <div class="card-header ">
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
                                            <td><?php
                                            if($project['webmaster_name']){
                                            echo htmlspecialchars($project['webmaster_name']);
                                            }else{
                                                echo '<span class="badge bg-danger">
                                                Deleted User</span>';

                                            }
                                            ?></td>
                                            <?php if (in_array($user_role, ['admin', 'qa_manager'])): ?>
                                                <td><?php echo htmlspecialchars($project['assigned_qa_username']); ?></td>
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




                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>