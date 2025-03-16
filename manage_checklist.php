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
        switch ($_POST['action']) { // Fixed syntax error here
            case 'add':
                $title = $conn->real_escape_string($_POST['title']);
                $stage = $conn->real_escape_string($_POST['stage']);
                // Don't use real_escape_string for rich text content
                $how_to_check = $_POST['how_to_check'];
                $how_to_fix = $_POST['how_to_fix'];

                // Get the highest order for this stage
                $order_query = "SELECT MAX(display_order) as max_order FROM checklist_items WHERE stage = ?";
                $stmt = $conn->prepare($order_query);
                $stmt->bind_param("s", $stage);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $display_order = ($row['max_order'] ?? 0) + 10; // Increment by 10 for spacing

                $query = "INSERT INTO checklist_items (title, stage, how_to_check, how_to_fix, created_by, display_order)
                         VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssii", $title, $stage, $how_to_check, $how_to_fix, $_SESSION['user_id'], $display_order);

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
                // Don't use real_escape_string for rich text content
                $how_to_check = $_POST['how_to_check'];
                $how_to_fix = $_POST['how_to_fix'];

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
                    removeChecklistItemSafely($item_id);
                    $success = "Checklist item archived successfully!";
                } catch (Exception $e) {
                    $error = "Error archiving checklist item: " . $e->getMessage();
                }
                break;

            case 'update_order':
                // Handle the order update from AJAX request
                if (isset($_POST['items']) && is_array($_POST['items'])) {
                    try {
                        $conn->begin_transaction();

                        foreach ($_POST['items'] as $item) {
                            $id = (int)$item['id'];
                            $order = (int)$item['order'];

                            $query = "UPDATE checklist_items SET display_order = ? WHERE id = ?";
                            $stmt = $conn->prepare($query);
                            $stmt->bind_param("ii", $order, $id);
                            $stmt->execute();
                        }

                        $conn->commit();
                        echo json_encode(['success' => true]);
                        exit;
                    } catch (Exception $e) {
                        $conn->rollback();
                        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                        exit;
                    }
                }
                break;
        }
    }
}

// Get all checklist items, now ordered by display_order
$query = "SELECT ci.*, u.username as created_by_name,
          (SELECT COUNT(DISTINCT pcs.project_id)
           FROM project_checklist_status pcs
           WHERE pcs.checklist_item_id = ci.id
           AND pcs.is_archived = 0) as usage_count,
          (SELECT COUNT(*)
           FROM comments c
           WHERE c.checklist_item_id = ci.id) as comment_count
          FROM checklist_items ci
          LEFT JOIN users u ON ci.created_by = u.id
          WHERE ci.is_archived = 0
          ORDER BY ci.stage, ci.display_order, ci.title";
$checklist_items = $conn->query($query);
require_once 'includes/header.php';
?>

<!-- Add this in the head section of your document -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">

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

            <!-- Save order button -->
            <div class="my-3 d-none" id="orderChangedAlert">
                <div class="alert alert-warning">
                    <i class="bi bi-info-circle me-2"></i>
                    Order has been changed.
                    <button type="button" class="btn btn-sm btn-primary ms-2" id="saveOrderBtn">
                        <i class="bi bi-save"></i> Save New Order
                    </button>
                </div>
            </div>

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
                                    <th width="5%"><i class="bi bi-grip-vertical"></i></th>
                                    <th width="650px">Title</th>
                                    <th>Usage Count</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody class="sortable-list" data-stage="<?php echo $stage; ?>">
                                <?php
                                $checklist_items->data_seek(0);
                                while ($item = $checklist_items->fetch_assoc()):
                                    if ($item['stage'] == $stage):
                                ?>
                                <tr class="sortable-item" data-id="<?php echo $item['id']; ?>" data-order="<?php echo $item['display_order']; ?>">
                                    <td class="drag-handle"><i class="bi bi-grip-vertical"></i></td>
                                    <td><?php echo htmlspecialchars($item['title']); ?></td>
                                    <td><?php echo $item['usage_count']; ?></td>
                                    <td><?php echo htmlspecialchars($item['created_by_name']); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-warning"
                                                onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-warning"
                                                onclick="showDeleteConfirmation(
                                                    <?php echo $item['id']; ?>,
                                                    '<?php echo htmlspecialchars(addslashes($item['title'])); ?>',
                                                    <?php echo $item['usage_count']; ?>
                                                )">
                                            <i class="bi bi-archive"></i> Archive
                                        </button>
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
            <form method="POST" id="addItemForm">
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
                        <div id="how_to_check_editor" class="quill-editor"></div>
                        <textarea class="d-none" id="how_to_check" name="how_to_check"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="how_to_fix" class="form-label">How to Fix</label>
                        <div id="how_to_fix_editor" class="quill-editor"></div>
                        <textarea class="d-none" id="how_to_fix" name="how_to_fix"></textarea>
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
            <form method="POST" id="editItemForm">
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
                        <div id="edit_how_to_check_editor" class="quill-editor"></div>
                        <textarea class="d-none" id="edit_how_to_check" name="how_to_check"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="edit_how_to_fix" class="form-label">How to Fix</label>
                        <div id="edit_how_to_fix_editor" class="quill-editor"></div>
                        <textarea class="d-none" id="edit_how_to_fix" name="how_to_fix"></textarea>
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


<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Archive Checklist Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Are you sure you want to archive this checklist item?</p>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This will:
                        <ul>
                            <li>Remove the item from future projects</li>
                            <li>Maintain existing project data and history</li>
                            <li>Preserve all associated comments</li>
                            <li>Not affect current project progress</li>
                        </ul>
                    </div>
                    <div class="item-details mb-3">
                        <strong>Item:</strong> <span id="delete-item-title"></span>
                    </div>
                    <div id="usage-info" class="alert alert-warning">
                        This item is currently used in <span id="usage-count"></span> project(s).
                        Archiving will maintain existing data but remove it from active use.
                    </div>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="item_id" id="delete-item-id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Archive Item</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.14.0/Sortable.min.js"></script>
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

<script>
    // Initialize Quill editors
    let addHowToCheckEditor, addHowToFixEditor, editHowToCheckEditor, editHowToFixEditor;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Quill for Add Item form
        addHowToCheckEditor = new Quill('#how_to_check_editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link'],
                    ['clean']
                ]
            },
            placeholder: 'Explain how to check this item...'
        });

        addHowToFixEditor = new Quill('#how_to_fix_editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link'],
                    ['clean']
                ]
            },
            placeholder: 'Explain how to fix this issue...'
        });

        // Initialize Quill for Edit Item form
        editHowToCheckEditor = new Quill('#edit_how_to_check_editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link'],
                    ['clean']
                ]
            }
        });

        editHowToFixEditor = new Quill('#edit_how_to_fix_editor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link'],
                    ['clean']
                ]
            }
        });

        // Handle form submission for Add Item
        document.getElementById('addItemForm').addEventListener('submit', function() {
            document.getElementById('how_to_check').value = addHowToCheckEditor.root.innerHTML;
            document.getElementById('how_to_fix').value = addHowToFixEditor.root.innerHTML;
        });

        // Handle form submission for Edit Item
        document.getElementById('editItemForm').addEventListener('submit', function() {
            document.getElementById('edit_how_to_check').value = editHowToCheckEditor.root.innerHTML;
            document.getElementById('edit_how_to_fix').value = editHowToFixEditor.root.innerHTML;
        });

        // Initialize sortable functionality
        const orderChangedAlert = document.getElementById('orderChangedAlert');
        const saveOrderBtn = document.getElementById('saveOrderBtn');
        let orderChanged = false;

        // Initialize Sortable on each stage's table
        document.querySelectorAll('.sortable-list').forEach(list => {
            new Sortable(list, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'bg-light',
                onEnd: function() {
                    // Show save button when order changes
                    orderChanged = true;
                    orderChangedAlert.classList.remove('d-none');
                }
            });
        });

        // Save new order
        saveOrderBtn.addEventListener('click', function() {
            const updatedItems = [];
            let order = 10; // Start with order 10

            // Process each stage separately to maintain group order
            document.querySelectorAll('.sortable-list').forEach(list => {
                const stage = list.dataset.stage;
                const items = list.querySelectorAll('.sortable-item');

                items.forEach((item, index) => {
                    updatedItems.push({
                        id: item.dataset.id,
                        order: order
                    });
                    order += 10; // Increment by 10 to allow room for future insertions
                });

                order = 10; // Reset order for next stage
            });

            // Send the new order to the server
            fetch('update_checklist_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'action': 'update_order',
                    'items': JSON.stringify(updatedItems)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hide the alert and set flag
                    orderChangedAlert.classList.add('d-none');
                    orderChanged = false;

                    // Show success message
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success mt-2';
                    successAlert.innerHTML = '<i class="bi bi-check-circle me-2"></i>Order saved successfully!';
                    document.querySelector('.card-body').prepend(successAlert);

                    // Remove success message after 3 seconds
                    setTimeout(() => successAlert.remove(), 3000);
                } else {
                    console.error('Error saving order:', data.error);

                    // Show error message
                    const errorAlert = document.createElement('div');
                    errorAlert.className = 'alert alert-danger mt-2';
                    errorAlert.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Error saving order!';
                    document.querySelector('.card-body').prepend(errorAlert);

                    // Remove error message after 3 seconds
                    setTimeout(() => errorAlert.remove(), 3000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });

        // Warn user if they try to leave with unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (orderChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

        // Add this to your DOMContentLoaded event
        // Initialize all modals
        var myModalEls = document.querySelectorAll('.modal');
        myModalEls.forEach(function(myModalEl) {
            new bootstrap.Modal(myModalEl);
        });

        // Add debug logging for modal opening
        document.querySelectorAll('[data-bs-toggle="modal"]').forEach(function(element) {
            element.addEventListener('click', function() {
                console.log('Modal trigger clicked:', this.getAttribute('data-bs-target'));
            });
        });
    });

    // Your existing script code
    function editItem(item) {
        document.getElementById('edit_item_id').value = item.id;
        document.getElementById('edit_title').value = item.title;
        document.getElementById('edit_stage').value = item.stage;

        // Set Quill editor content instead of textarea
        editHowToCheckEditor.root.innerHTML = item.how_to_check;
        editHowToFixEditor.root.innerHTML = item.how_to_fix;

        new bootstrap.Modal(document.getElementById('editItemModal')).show();
    }

    function showDeleteConfirmation(itemId, itemTitle, usageCount) {
        // Set values in the modal
        document.getElementById('delete-item-id').value = itemId;
        document.getElementById('delete-item-title').textContent = itemTitle;
        document.getElementById('usage-count').textContent = usageCount;

        // Show or hide usage info based on count
        document.getElementById('usage-info').style.display = usageCount > 0 ? 'block' : 'none';

        // Show the modal using Bootstrap's API
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        deleteModal.show();
    }

    // Rest of your existing functions...
</script>
<style>
    /* Add these styles to fix Quill editor display */
    .quill-editor {
        display: block;
        height: 200px;
        margin-bottom: 15px;
        border-radius: 0.25rem;
        background-color: white;
    }
    .ql-container {
        height: calc(100% - 43px); /* 43px is the height of the toolbar */
    }
    .ql-editor {
        min-height: 150px;
        font-size: 14px;
        line-height: 1.5;
    }

    /* Fix modal z-index issues */
    .modal {
        z-index: 1050;
    }
    .modal-backdrop {
        z-index: 1040;
    }

    /* Make sure Quill toolbar doesn't break modal scrolling */
    .ql-toolbar {
        z-index: 1;
        background: white;
        border-top-left-radius: 0.25rem;
        border-top-right-radius: 0.25rem;
    }

    /* Existing styles */
    .drag-handle {
        cursor: grab;
        color: #aaa;
    }
    .drag-handle:hover {
        color: #666;
    }
    .sortable-ghost {
        background-color: #f8f9fa !important;
        opacity: 0.8;
    }
    .sortable-chosen {
        background-color: #e9ecef;
    }
    tr.sortable-drag {
        opacity: 0.9;
        background-color: #fff !important;
        box-shadow: 0 0 10px rgba(0,0,0,0.2);
    }
    /* Quill editor styles */
    .quill-editor {
        height: 200px;
        margin-bottom: 15px;
    }
    .ql-editor {
        min-height: 150px;
    }
    /* Style for rendered rich text in the table */
    .rich-text-content {
        max-height: 100px;
        overflow-y: auto;
    }
    .rich-text-content p {
        margin-bottom: 0.5em;
    }
    .rich-text-content ul,
    .rich-text-content ol {
        padding-left: 1.5em;
        margin-bottom: 0.5em;
    }
</style>
</body>
</html>