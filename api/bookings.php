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
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'accept':
        $bookingId = (int)$_POST['id'];
        if ($bookingObj->accept($bookingId, getUserId())) {
            $response['success'] = true;
            $response['message'] = 'Booking accepted!';
        } else {
            $response['message'] = 'Failed to accept booking';
        }
        break;
        
    case 'reject':
        $bookingId = (int)$_POST['id'];
        if ($bookingObj->reject($bookingId, getUserId())) {
            $response['success'] = true;
            $response['message'] = 'Booking rejected!';
        } else {
            $response['message'] = 'Failed to reject booking';
        }
        break;
        
    case 'complete':
        $bookingId = (int)$_POST['id'];
        if ($bookingObj->complete($bookingId, getUserId())) {
            $response['success'] = true;
            $response['message'] = 'Session completed! Points transferred.';
        } else {
            $response['message'] = 'Failed to complete session. Make sure learner has enough points.';
        }
        break;
        
    case 'cancel':
        $bookingId = (int)$_POST['id'];
        if ($bookingObj->cancel($bookingId, getUserId())) {
            $response['success'] = true;
            $response['message'] = 'Booking cancelled!';
        } else {
            $response['message'] = 'Failed to cancel booking';
        }
        break;
        
    default:
        $response['message'] = 'Invalid action';
}

echo json_encode($response);