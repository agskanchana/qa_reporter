<?php

require_once 'includes/config.php';
checkPermission(['admin']);
$user_role = getUserRole();
$user_id = $_SESSION['user_id'];

$error = '';
$success = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'create') {
        $username = $conn->real_escape_string($_POST['username']);
        $email = $conn->real_escape_string($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $conn->real_escape_string($_POST['role']);

        // Double-check both email and username
        $check_query = "SELECT COUNT(*) as count FROM users WHERE email = ? OR username = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ss", $email, $username);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        $count = $result->fetch_assoc()['count'];

        if ($count > 0) {
            $error = "Username or email already exists!";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $password, $role);

            if ($stmt->execute()) {
                $success = "User created successfully!";
            } else {
                $error = "Error creating user: " . $conn->error;
            }
        }
    }

    // Handle user deletion
    if ($_POST['action'] == 'delete') {
        $user_id = (int)$_POST['user_id'];

        // Prevent admin from deleting themselves
        if ($user_id != $_SESSION['user_id']) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);

            if ($stmt->execute()) {
                $success = "User deleted successfully!";
            } else {
                $error = "Error deleting user: " . $conn->error;
            }
        } else {
            $error = "You cannot delete your own admin account!";
        }
    }
}

// Get all users
$query = "SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC";
$users = $conn->query($query);
require_once 'includes/header.php';
?>


    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h2>User Management</h2>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="bi bi-person-plus"></i> Add New User
                </button>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge bg-<?php
                                        $role_class = 'secondary';
                                        if ($user['role'] === 'admin') {
                                            $role_class = 'danger';
                                        } elseif ($user['role'] === 'qa_manager') {
                                            $role_class = 'primary';
                                        } elseif ($user['role'] === 'qa_reporter') {
                                            $role_class = 'success';
                                        } elseif ($user['role'] === 'webmaster') {
                                            $role_class = 'info';
                                        }
                                        echo $role_class;
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="createUserForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                            <div class="invalid-feedback"></div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="qa_manager">QA Manager</option>
                                <option value="qa_reporter">QA Reporter</option>
                                <option value="webmaster">Webmaster</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const emailInput = document.getElementById('email');
        const usernameInput = document.getElementById('username');
        const createForm = document.getElementById('createUserForm');
        const submitButton = createForm.querySelector('button[type="submit"]');
        let emailTimeoutId, usernameTimeoutId;
        let isSubmitting = false;

        async function checkAvailability(input, endpoint) {
            if (!input.value) return true;

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `${input.name}=${encodeURIComponent(input.value)}`
                });
                const data = await response.json();

                input.classList.remove('is-invalid', 'is-valid');
                const feedback = input.nextElementSibling;

                if (data.exists) {
                    input.classList.add('is-invalid');
                    if (feedback) {
                        feedback.textContent = `This ${input.name} is already taken`;
                    }
                    return false;
                } else {
                    input.classList.add('is-valid');
                    if (feedback) {
                        feedback.textContent = '';
                    }
                    return true;
                }
            } catch (error) {
                console.error('Error checking availability:', error);
                return false;
            }
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        const debouncedEmailCheck = debounce(() => {
            checkAvailability(emailInput, 'check_email.php').then(updateSubmitButton);
        }, 500);

        const debouncedUsernameCheck = debounce(() => {
            checkAvailability(usernameInput, 'check_username.php').then(updateSubmitButton);
        }, 500);

        emailInput.addEventListener('input', debouncedEmailCheck);
        usernameInput.addEventListener('input', debouncedUsernameCheck);

        function updateSubmitButton() {
            const isEmailValid = !emailInput.classList.contains('is-invalid');
            const isUsernameValid = !usernameInput.classList.contains('is-invalid');
            submitButton.disabled = !(isEmailValid && isUsernameValid);
        }

        createForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            if (isSubmitting) return;
            isSubmitting = true;

            const emailValid = await checkAvailability(emailInput, 'check_email.php');
            const usernameValid = await checkAvailability(usernameInput, 'check_username.php');

            if (emailValid && usernameValid) {
                this.submit();
            } else {
                isSubmitting = false;
                updateSubmitButton();
            }
        });

        // Reset form when modal is hidden
        const addUserModal = document.getElementById('addUserModal');
        addUserModal.addEventListener('hidden.bs.modal', function () {
            createForm.reset();
            emailInput.classList.remove('is-invalid', 'is-valid');
            usernameInput.classList.remove('is-invalid', 'is-valid');
            submitButton.disabled = false;
            isSubmitting = false;
        });
    });
    </script>
</body>
</html>