<?php
// Get all QA reporters for assignment dropdown if not already loaded
if (!isset($qa_reporters) || empty($qa_reporters)) {
    $qa_reporters = [];
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE role IN ('qa_reporter', 'qa_manager') ORDER BY username");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $qa_reporters[] = $row;
    }
}
?>

<!-- QA Assignment Modal -->
<div class="modal fade" id="assignQAModal" tabindex="-1" aria-labelledby="assignQAModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assignQAModalLabel">
                    <i class="bi bi-person-plus me-2"></i> Assign QA Reporter
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="post">
                <div class="modal-body">
                    <input type="hidden" name="project_id" id="modal-project-id">

                    <div class="mb-3">
                        <label for="modal-project-name" class="form-label">Project</label>
                        <input type="text" class="form-control" id="modal-project-name" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="qa_user_id" class="form-label">Assign to QA Reporter</label>
                        <select class="form-select" name="qa_user_id" id="qa_user_id" required>
                            <option value="" selected disabled>Select a QA Reporter</option>
                            <?php foreach ($qa_reporters as $reporter): ?>
                                <option value="<?php echo $reporter['id']; ?>">
                                    <?php echo htmlspecialchars($reporter['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            Select the QA reporter who will handle this project's quality assurance.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_qa" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i> Assign QA
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>