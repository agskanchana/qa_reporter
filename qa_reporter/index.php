<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and has qa_reporter role
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit();
}

$user_role = getUserRole();
$user_id = $_SESSION['user_id'];

// Only allow qa_reporter or qa_manager access to this page
if ($user_role !== 'qa_reporter' && $user_role !== 'qa_manager') {
    // Redirect to appropriate dashboard based on role
    switch ($user_role) {
        case 'admin':
            header("Location: ../admin/index.php");
            break;
        case 'webmaster':
            header("Location: ../webmaster/index.php");
            break;
        default:
            header("Location: ../index.php");
            break;
    }
    exit();
}

// Get auto-assign settings
$auto_assign_wp = false;
$auto_assign_golive = false;

$auto_assign_query = "SELECT setting_key, is_enabled FROM auto_assign_to_admin";
$auto_assign_result = $conn->query($auto_assign_query);
if ($auto_assign_result && $auto_assign_result->num_rows > 0) {
    while ($row = $auto_assign_result->fetch_assoc()) {
        if ($row['setting_key'] == 'wp_conversion') {
            $auto_assign_wp = (bool)$row['is_enabled'];
        } elseif ($row['setting_key'] == 'golive') {
            $auto_assign_golive = (bool)$row['is_enabled'];
        }
    }
}

$page_title = "QA Reporter Dashboard";
include_once '../includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">QA Reporter Dashboard</h1>
        </div>
    </div>

    <!-- Stats Cards -->
    <?php
    //include_once '../includes/qa_reporter/widgets/stats_cards.php';
    ?>

    <div class="row mt-4">
        <!-- Left Column -->
        <div class="col-md-4">
            <!-- Notifications Widget -->
            <?php include_once '../includes/qa_reporter/widgets/notifications.php'; ?>

            <!-- Upcoming Deadlines Widget -->
            <?php include_once '../includes/qa_reporter/widgets/upcoming_deadlines.php'; ?>
        </div>

        <!-- Right Column -->
        <div class="col-md-8">
            <!-- My Assigned Projects Widget -->
            <?php include_once '../includes/qa_reporter/widgets/assigned_projects.php'; ?>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom scripts -->
<script src="<?php echo BASE_URL; ?>/assets/js/qa-reporter-main.js"></script>

</body>
</html>