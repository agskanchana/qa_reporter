<div class="modal fade" id="assignQAModal" tabindex="-1" aria-labelledby="assignQAModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?php echo BASE_URL; ?>/qa_manager/index.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignQAModalLabel">Assign QA Reporter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal-project-name" class="form-label">Project</label>
                        <input type="text" class="form-control" id="modal-project-name" readonly>
                        <input type="hidden" name="project_id" id="modal-project-id">
                    </div>
                    <div class="mb-3">
                        <label for="qa_user_id" class="form-label">Select QA Reporter</label>
                        <select class="form-select" id="qa_user_id" name="qa_user_id">
                            <option value="">-- Select QA Reporter --</option>
                            <?php foreach ($qa_reporters as $reporter): ?>
                                <option value="<?php echo $reporter['id']; ?>"><?php echo htmlspecialchars($reporter['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="assign_qa" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>