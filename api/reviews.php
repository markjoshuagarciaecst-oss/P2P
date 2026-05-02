<?php
// API - Reviews endpoint
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../classes/Review.php';

session_start();

$response = ['success' => false, 'message' => ''];

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$reviewObj = new Review();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        $bookingId = (int)$_POST['booking_id'];
        $rating = (int)$_POST['rating'];
        $comment = sanitize($_POST['comment'] ?? '');
        
        if (!$rating || $rating < 1 || $rating > 5) {
            $response['message'] = 'Please select a valid rating';
            echo json_encode($response);
            exit;
        }
        
        if (!$reviewObj->canReview($bookingId, getUserId())) {
            $response['message'] = 'You cannot review this booking';
            echo json_encode($response);
            exit;
        }
        
        // Get booking to find reviewee
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            $response['message'] = 'Booking not found';
            echo json_encode($response);
            exit;
        }
        
        $revieweeId = ($booking['learner_id'] == getUserId()) ? $booking['teacher_id'] : $booking['learner_id'];
        
        if ($reviewObj->create($bookingId, getUserId(), $revieweeId, $rating, $comment)) {
            $response['success'] = true;
            $response['message'] = 'Review submitted!';
        } else {
            $response['message'] = 'Failed to submit review';
        }
        break;
        
    default:
        $response['message'] = 'Invalid action';
}

echo json_encode($response);