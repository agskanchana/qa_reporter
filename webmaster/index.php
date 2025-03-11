<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a webmaster
if (!isLoggedIn() || getUserRole() !== 'webmaster') {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$user_role = getUserRole();
$webmaster_id = $_SESSION['user_id'];
$webmaster_name = $_SESSION['username'];

// Include header
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

    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h2 mb-0">Welcome, <?php echo htmlspecialchars($webmaster_name); ?></h1>
            <p class="text-muted">Webmaster Dashboard</p>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-4">
            <!-- Notifications Widget -->
            <?php include_once '../includes/webmaster/widgets/notifications.php'; ?>

            <!-- Most Failing Checklist Items Widget -->
            <?php include_once '../includes/webmaster/widgets/most_failing_checklist.php'; ?>

            <!-- Will add Upcoming Deadlines widget next -->
        </div>

        <!-- Right Column -->
        <div class="col-lg-8">
            <!-- Active Projects Widget -->
            <?php include_once '../includes/webmaster/widgets/active_projects.php'; ?>

            <!-- Completed Projects Widget -->
            <?php include_once '../includes/webmaster/widgets/completed_projects.php'; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo BASE_URL; ?>/assets/js/dashboard.js"></script>
</body>
</html>