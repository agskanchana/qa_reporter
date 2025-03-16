<?php
// filepath: c:\wamp64\www\qa_reporter\update_checklist_order.php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check permissions
if (!isLoggedIn() || !in_array(getUserRole(), ['admin', 'qa_manager'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Handle the order update from AJAX request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['items'])) {
    try {
        $items = json_decode($_POST['items'], true);
        if (!is_array($items)) {
            throw new Exception('Invalid data format');
        }

        $conn->begin_transaction();

        foreach ($items as $item) {
            $id = (int)$item['id'];
            $order = (int)$item['order'];

            $query = "UPDATE checklist_items SET display_order = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $order, $id);
            $stmt->execute();
        }

        $conn->commit();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'error' => 'Invalid request']);
exit;