<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

// Проверка авторизации
// if (!isset($_SESSION['user_id'])) {
//     echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
//     exit;
// }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $recipe_id = $_POST['recipe_id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $instructions = $_POST['instructions'];
    $image_url = $_POST['image_url'];
    
    // Проверяем владельца рецепта
    // $checkOwner = $pdo->prepare("SELECT author_id FROM recipes WHERE id = ?");
    // $checkOwner->execute([$recipe_id]);
    // $recipe = $checkOwner->fetch();

    // if (!$recipe || $recipe['author_id'] != $_SESSION['user_id']) {
    //     echo json_encode(['status' => 'error', 'message' => 'Unauthorized access to this recipe']);
    //     exit;
    // }

    // Обновляем основную информацию о рецепте
    $stmt = $pdo->prepare("
        UPDATE recipes 
        SET name = ?, description = ?, instructions = ?, image_url = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$name, $description, $instructions, $image_url, $recipe_id]);
    
    // Обрабатываем ингредиенты
    if (isset($_POST['ingredients']) && is_array($_POST['ingredients'])) {
        // Удаляем старые связи
        $stmt = $pdo->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?");
        $stmt->execute([$recipe_id]);
        
        // Добавляем новые ингредиенты
        $insertIngredient = $pdo->prepare("
            INSERT INTO recipe_ingredients (recipe_id, ingredient_id, amount, unit) 
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($_POST['ingredients'] as $key => $ingredient_id) {
            if (empty($ingredient_id)) continue;
            
            $amount = $_POST['amounts'][$key] ?? null;
            $unit = $_POST['units'][$key] ?? null;
            
            $insertIngredient->execute([
                $recipe_id,
                $ingredient_id,
                $amount,
                $unit
            ]);
        }
    }
    
    // Обрабатываем категории
    if (isset($_POST['categories']) && is_array($_POST['categories'])) {
        // Удаляем старые связи
        $stmt = $pdo->prepare("DELETE FROM recipe_categories WHERE recipe_id = ?");
        $stmt->execute([$recipe_id]);
        
        // Добавляем новые категории
        $insertCategory = $pdo->prepare("
            INSERT INTO recipe_categories (recipe_id, category_id) 
            VALUES (?, ?)
        ");
        
        foreach ($_POST['categories'] as $category_id) {
            if (empty($category_id)) continue;
            $insertCategory->execute([$recipe_id, $category_id]);
        }
    }
    
    $pdo->commit();
    echo json_encode([
        'status' => 'success', 
        'message' => 'Recipe updated successfully'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => 'error', 
        'message' => 'Error updating recipe: ' . $e->getMessage()
    ]);
} 