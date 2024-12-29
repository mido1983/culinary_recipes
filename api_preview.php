<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$apiUrl = $_POST['api_url'] ?? null;

if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
    echo json_encode(["status" => "error", "message" => "Invalid URL format"]);
    exit;
}

$response = @file_get_contents($apiUrl);
if ($response === false) {
    echo json_encode(["status" => "error", "message" => "Failed to fetch data from API"]);
    exit;
}

$data = json_decode($response, true);
if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid API response"]);
    exit;
}

echo json_encode([
    "status" => "success",
    "data" => $data
]); 