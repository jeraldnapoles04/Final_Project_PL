<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'mark_read':
            $notification_id = filter_var($_POST['notification_id'], FILTER_SANITIZE_NUMBER_INT);
            try {
                $stmt = $conn->prepare("CALL mark_notification_read(?)");
                $stmt->execute([$notification_id]);
                $response = ['success' => true, 'message' => 'Notification marked as read'];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Failed to mark notification as read'];
            }
            break;
            
        case 'mark_all_read':
            try {
                $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $response = ['success' => true, 'message' => 'All notifications marked as read'];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Failed to mark all notifications as read'];
            }
            break;
            
        case 'delete':
            $notification_id = filter_var($_POST['notification_id'], FILTER_SANITIZE_NUMBER_INT);
            try {
                $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->execute([$notification_id, $user_id]);
                $response = ['success' => true, 'message' => 'Notification deleted'];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Failed to delete notification'];
            }
            break;
            
        case 'get_notifications':
            try {
                $stmt = $conn->prepare("
                    SELECT * FROM notifications 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 10
                ");
                $stmt->execute([$user_id]);
                $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response = [
                    'success' => true,
                    'notifications' => $notifications
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Failed to fetch notifications'];
            }
            break;
            
        case 'get_unread_count':
            try {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM notifications 
                    WHERE user_id = ? AND is_read = FALSE
                ");
                $stmt->execute([$user_id]);
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                $response = [
                    'success' => true,
                    'count' => $count
                ];
            } catch (PDOException $e) {
                $response = ['success' => false, 'message' => 'Failed to fetch unread count'];
            }
            break;
    }
}

header('Content-Type: application/json');
echo json_encode($response); 