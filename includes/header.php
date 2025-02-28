<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <base href="<?php echo BASE_URL; ?>">
    <link rel="icon" href="<?php echo BASE_URL; ?>/images/favicon.ico">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo url('dashboard.php'); ?>">QA Reporter</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo url('dashboard.php'); ?>">Dashboard</a>
                    </li>
                    <?php if (in_array($user_role, ['admin', 'qa_manager'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('projects.php'); ?>">Projects</a>
                    </li>
                        <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('users.php'); ?>">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('reports.php'); ?>">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo url('manage_checklist.php'); ?>">Checklist</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('admin/check_update.php'); ?>">
                            <i class="bi bi-cloud-download"></i> Check for Updates
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="navbar-nav">
                    <span class="nav-item nav-link text-light">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a class="nav-link" href="<?php echo url('logout.php'); ?>">Logout</a>
                </div>
            </div>
        </div>
    </nav>
</body>
</html>
