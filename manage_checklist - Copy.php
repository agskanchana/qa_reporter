<?php
// manage_checklist.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
// Check permissions
checkPermission(['admin', 'qa_manager']);
$user_role = getUserRole();
$user_id = $_SESSION['user_id'];

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $title = $conn->real_escape_string($_POST['title']);
                $stage = $conn->real_escape_string($_POST['stage']);
                $how_to_check = $conn->real_escape_string($_POST['how_to_check']);
                $how_to_fix = $conn->real_escape_string($_POST['how_to_fix']);

                $query = "INSERT INTO checklist_items (title, stage, how_to_check, how_to_fix, created_by)
                         VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssi", $title, $stage, $how_to_check, $how_to_fix, $_SESSION['user_id']);

                if ($stmt->execute()) {
                    syncProjectChecklist();
                    $success = "Checklist item added successfully!";
                } else {
                    $error = "Error adding checklist item: " . $conn->error;
                }
                break;

            case 'edit':
                $id = (int)$_POST['item_id'];
                $title = $conn->real_escape_string($_POST['title']);
                $stage = $conn->real_escape_string($_POST['stage']);
                $how_to_check = $conn->real_escape_string($_POST['how_to_check']);
                $how_to_fix = $conn->real_escape_string($_POST['how_to_fix']);

                $query = "UPDATE checklist_items
                         SET title = ?, stage = ?, how_to_check = ?, how_to_fix = ?
                         WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssi", $title, $stage, $how_to_check, $how_to_fix, $id);

                if ($stmt->execute()) {
                    $success = "Checklist item updated successfully!";
                } else {
                    $error = "Error updating checklist item: " . $conn->error;
                }
                break;

            case 'delete':
                $item_id = (int)$_POST['item_id'];

                try {
                    removeChecklistItemFromProjects($item_id);
                    $success = "Checklist item deleted successfully!";
                } catch (Exception $e) {
                    $error = "Error deleting checklist item: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get all checklist items
$query = "SELECT ci.*, u.username as created_by_name,
          (SELECT COUNT(DISTINCT pcs.project_id)
           FROM project_checklist_status pcs
           WHERE pcs.checklist_item_id = ci.id) as usage_count,
          (SELECT COUNT(*)
           FROM comments c
           WHERE c.checklist_item_id = ci.id) as comment_count
          FROM checklist_items ci
          LEFT JOIN users u ON ci.created_by = u.id
          ORDER BY ci.stage, ci.title";
$checklist_items = $conn->query($query);
require_once 'includes/header.php';
?>


    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h2>Manage Checklist Items</h2>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="bi bi-plus-circle"></i> Add New Item
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
                <ul class="nav nav-tabs" id="stageTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="wp-tab" data-bs-toggle="tab" href="#wp" role="tab">
                            WP Conversion
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="page-tab" data-bs-toggle="tab" href="#page" role="tab">
                            Page Creation
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="golive-tab" data-bs-toggle="tab" href="#golive" role="tab">
                            Golive
                        </a>
                    </li>
                </ul>

                <div class="tab-content mt-3">
                    <?php
                    $stages = ['wp_conversion', 'page_creation', 'golive'];
                    foreach ($stages as $stage):
                        $active = $stage == 'wp_conversion' ? 'show active' : '';
                    ?>
                    <div class="tab-pane fade <?php echo $active; ?>"
                         id="<?php echo explode('_', $stage)[0]; ?>" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Usage Count</th>
                                        <th>Created By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $checklist_items->data_seek(0);
                                    while ($item = $checklist_items->fetch_assoc()):
                                        if ($item['stage'] == $stage):
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['title']); ?></td>
                                        <td><?php echo $item['usage_count']; ?></td>
                                        <td><?php echo htmlspecialchars($item['created_by_name']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-warning"
                                                    onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <?php if ($item['usage_count'] == 0): ?>
                                                <button type="button" class="btn btn-sm btn-danger"
                                                        onclick="showDeleteConfirmation(<?php echo $item['id']; ?>,
                                                                '<?php echo htmlspecialchars(addslashes($item['title'])); ?>',
                                                                <?php echo $item['usage_count']; ?>)">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-danger" disabled
                                                        title="Cannot delete: Item is being used in <?php echo $item['usage_count']; ?> project(s)">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php
                                        endif;
                                    endwhile;
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Checklist Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">

                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>

                        <div class="mb-3">
                            <label for="stage" class="form-label">Stage</label>
                            <select class="form-select" id="stage" name="stage" required>
                                <option value="wp_conversion">WP Conversion</option>
                                <option value="page_creation">Page Creation</option>
                                <option value="golive">Golive</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="how_to_check" class="form-label">How to Check</label>
                            <textarea class="form-control" id="how_to_check" name="how_to_check" rows="3" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="how_to_fix" class="form-label">How to Fix</label>
                            <textarea class="form-control" id="how_to_fix" name="how_to_fix" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div class="modal fade" id="editItemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Checklist Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="item_id" id="edit_item_id">

                        <div class="mb-3">
                            <label for="edit_title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_stage" class="form-label">Stage</label>
                            <select class="form-select" id="edit_stage" name="stage" required>
                                <option value="wp_conversion">WP Conversion</option>
                                <option value="page_creation">Page Creation</option>
                                <option value="golive">Golive</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="edit_how_to_check" class="form-label">How to Check</label>
                            <textarea class="form-control" id="edit_how_to_check" name="how_to_check" rows="3" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="edit_how_to_fix" class="form-label">How to Fix</label>
                            <textarea class="form-control" id="edit_how_to_fix" name="how_to_fix" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this checklist item?</p>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i> This will:
                    <ul>
                        <li>Remove the item from all projects</li>
                        <li>Delete all associated comments</li>
                        <li>Delete all status history</li>
                    </ul>
                    This action cannot be undone.
                </div>
                <div class="item-details">
                    <strong>Item:</strong> <span id="delete-item-title"></span>
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="item_id" id="delete-item-id">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function editItem(item) {
            document.getElementById('edit_item_id').value = item.id;
            document.getElementById('edit_title').value = item.title;
            document.getElementById('edit_stage').value = item.stage;
            document.getElementById('edit_how_to_check').value = item.how_to_check;
            document.getElementById('edit_how_to_fix').value = item.how_to_fix;

            new bootstrap.Modal(document.getElementById('editItemModal')).show();
        }
        function confirmDelete(itemId, itemTitle) {
            document.getElementById('delete-item-id').value = itemId;
            document.getElementById('delete-item-title').textContent = itemTitle;
            new bootstrap.Modal(document.getElementById('deleteConfirmModal')).show();
        }

        // Add this if you want to show usage statistics in the delete confirmation
        function showDeleteConfirmation(itemId, itemTitle, usageCount) {
            document.getElementById('delete-item-id').value = itemId;
            document.getElementById('delete-item-title').textContent = itemTitle;

            const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            modal.show();
        }
    </script>
</body>
</html>