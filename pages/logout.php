<?php
require_once '../config/database.php';
require_once '../classes/User.php';

// Logout user
session_destroy();
header("Location: " . APP_URL . "/index.php");
exit();
?>