<?php
// API - Bookings endpoint
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../classes/Booking.php';
require_once '../classes/Notification.php';

session_start();

$response = ['success' => false, 'message' => ''];

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$bookingObj = new Booking();
$db         = Database::getInstance();
$action     = $_POST['action'] ?? $_GET['action'] ?? '';

// Helper: check if a column exists in a table
function colExists($db, $table, $col) {
    $s = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $s->execute([$table, $col]);
    return (bool)$s->fetchColumn();
}

$hasConfirmed = colExists($db, 'bookings', 'teacher_confirmed');

switch ($action) {

    // ------------------------------------------------------------------
    case 'accept':
        $bookingId = (int)$_POST['id'];
        if ($bookingObj->accept($bookingId, getUserId())) {
            $response['success'] = true;
            $response['message'] = 'Booking accepted!';
        } else {
            $response['message'] = 'Failed to accept booking.';
        }
        break;

    // ------------------------------------------------------------------
    case 'reject':
        $bookingId = (int)$_POST['id'];
        if ($bookingObj->reject($bookingId, getUserId())) {
            $response['success'] = true;
            $response['message'] = 'Booking rejected!';
        } else {
            $response['message'] = 'Failed to reject booking.';
        }
        break;

    // ------------------------------------------------------------------
    // Teacher marks session complete — notifies learner to confirm
    // ------------------------------------------------------------------
    case 'teacher_confirm':
        $bookingId = (int)$_POST['id'];
        $userId    = getUserId();

        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $response['message'] = 'Booking not found.';
            break;
        }
        if ($booking['teacher_id'] != $userId) {
            $response['message'] = 'You are not the teacher for this booking.';
            break;
        }
        if ($booking['status'] !== 'accepted') {
            $response['message'] = 'Booking must be accepted before it can be completed.';
            break;
        }
        // Check if teacher already confirmed (column may not exist)
        if ($hasConfirmed && !empty($booking['teacher_confirmed'])) {
            $response['success'] = true;
            $response['message'] = 'You have already confirmed. Waiting for the learner to confirm.';
            $response['waiting'] = true;
            break;
        }

        if ($bookingObj->teacherConfirm($bookingId, $userId)) {
            $response['success'] = true;
            $response['message'] = 'Confirmed! The learner has been notified and must confirm to transfer points.';
            $response['waiting'] = true;
        } else {
            $response['message'] = 'Failed to confirm session.';
        }
        break;

    // ------------------------------------------------------------------
    // Learner confirms — FINAL step, always triggers point transfer
    // ------------------------------------------------------------------
    case 'learner_confirm':
        $bookingId = (int)$_POST['id'];
        $userId    = getUserId();

        $stmt = $db->prepare("SELECT * FROM bookings WHERE id = ?");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $response['message'] = 'Booking not found.';
            break;
        }
        if ($booking['learner_id'] != $userId) {
            $response['message'] = 'You are not the learner for this booking.';
            break;
        }
        if ($booking['status'] !== 'accepted') {
            $response['message'] = 'Booking must be accepted before it can be completed.';
            break;
        }

        if ($bookingObj->learnerConfirm($bookingId, $userId)) {
            $response['success']   = true;
            $response['message']   = 'Session completed! Points have been transferred.';
            $response['completed'] = true;
        } else {
            $response['message'] = 'Failed to complete session. Make sure you have enough points.';
        }
        break;

    // ------------------------------------------------------------------
    case 'cancel':
        $bookingId = (int)$_POST['id'];
        if ($bookingObj->cancel($bookingId, getUserId())) {
            $response['success'] = true;
            $response['message'] = 'Booking cancelled!';
        } else {
            $response['message'] = 'Failed to cancel booking.';
        }
        break;

    // ------------------------------------------------------------------
    default:
        $response['message'] = 'Invalid action.';
}

echo json_encode($response);
