<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../db_connect.php';

// Parse the URL
$request = parse_url($_SERVER['REQUEST_URI']);
$path = $request['path'];
$path_parts = explode('/', trim($path, '/'));
$endpoint = $path_parts[1] ?? ''; // Assuming 'api' is first part
$resource = $path_parts[2] ?? '';
$id = $path_parts[3] ?? null;

// Get query parameters
$params = [];
if (isset($request['query'])) {
    parse_str($request['query'], $params);
}

try {
    switch ($endpoint) {
        case 'recipes':
            include_once 'recipes.php';
            break;
            
        case 'categories':
            include_once 'categories.php';
            break;
            
        case 'ingredients':
            include_once 'ingredients.php';
            break;
            
        case 'auth':
            include_once 'auth.php';
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 