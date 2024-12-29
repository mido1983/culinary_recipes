<?php
if (!defined('INCLUDED')) {
    http_response_code(403);
    exit('Direct access not permitted');
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if ($id) {
            // Get single recipe
            $query = "
                SELECT 
                    r.*,
                    u.username as author_name,
                    GROUP_CONCAT(DISTINCT i.name) as ingredients,
                    GROUP_CONCAT(DISTINCT c.name) as categories,
                    COUNT(DISTINCT rv.id) as review_count,
                    AVG(rv.rating) as average_rating
                FROM recipes r
                LEFT JOIN users u ON r.author_id = u.id
                LEFT JOIN recipe_ingredients ri ON r.id = ri.recipe_id
                LEFT JOIN ingredients i ON ri.ingredient_id = i.id
                LEFT JOIN recipe_categories rc ON r.id = rc.recipe_id
                LEFT JOIN categories c ON rc.category_id = c.id
                LEFT JOIN recipe_reviews rv ON r.id = rv.recipe_id
                WHERE r.id = ?
                GROUP BY r.id
            ";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$id]);
            $recipe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($recipe) {
                // Get detailed ingredients
                $ingredients_query = "
                    SELECT i.*, ri.amount, ri.unit
                    FROM recipe_ingredients ri
                    JOIN ingredients i ON ri.ingredient_id = i.id
                    WHERE ri.recipe_id = ?
                ";
                $stmt = $pdo->prepare($ingredients_query);
                $stmt->execute([$id]);
                $recipe['detailed_ingredients'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode($recipe);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Recipe not found']);
            }
        } else {
            // List recipes with filtering and pagination
            $page = max(1, intval($params['page'] ?? 1));
            $per_page = min(50, max(1, intval($params['per_page'] ?? 12)));
            $offset = ($page - 1) * $per_page;
            
            $where_conditions = [];
            $where_params = [];
            
            // Search
            if (!empty($params['search'])) {
                $where_conditions[] = "(r.name LIKE ? OR r.description LIKE ?)";
                $search_term = "%{$params['search']}%";
                $where_params[] = $search_term;
                $where_params[] = $search_term;
            }
            
            // Category filter
            if (!empty($params['category'])) {
                $where_conditions[] = "c.id = ?";
                $where_params[] = $params['category'];
            }
            
            // Ingredient filter
            if (!empty($params['ingredient'])) {
                $where_conditions[] = "i.id = ?";
                $where_params[] = $params['ingredient'];
            }
            
            // Rating filter
            if (!empty($params['min_rating'])) {
                $where_conditions[] = "AVG(rv.rating) >= ?";
                $where_params[] = $params['min_rating'];
            }
            
            $where_clause = !empty($where_conditions) 
                ? "WHERE " . implode(" AND ", $where_conditions) 
                : "";
            
            $query = "
                SELECT 
                    r.*,
                    u.username as author_name,
                    GROUP_CONCAT(DISTINCT i.name) as ingredients,
                    GROUP_CONCAT(DISTINCT c.name) as categories,
                    COUNT(DISTINCT rv.id) as review_count,
                    AVG(rv.rating) as average_rating
                FROM recipes r
                LEFT JOIN users u ON r.author_id = u.id
                LEFT JOIN recipe_ingredients ri ON r.id = ri.recipe_id
                LEFT JOIN ingredients i ON ri.ingredient_id = i.id
                LEFT JOIN recipe_categories rc ON r.id = rc.recipe_id
                LEFT JOIN categories c ON rc.category_id = c.id
                LEFT JOIN recipe_reviews rv ON r.id = rv.recipe_id
                $where_clause
                GROUP BY r.id
                ORDER BY r.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute([...$where_params, $per_page, $offset]);
            $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count for pagination
            $count_query = "
                SELECT COUNT(DISTINCT r.id) as total
                FROM recipes r
                LEFT JOIN recipe_ingredients ri ON r.id = ri.recipe_id
                LEFT JOIN ingredients i ON ri.ingredient_id = i.id
                LEFT JOIN recipe_categories rc ON r.id = rc.recipe_id
                LEFT JOIN categories c ON rc.category_id = c.id
                $where_clause
            ";
            $stmt = $pdo->prepare($count_query);
            $stmt->execute($where_params);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            echo json_encode([
                'data' => $recipes,
                'pagination' => [
                    'total' => (int)$total,
                    'per_page' => $per_page,
                    'current_page' => $page,
                    'last_page' => ceil($total / $per_page)
                ]
            ]);
        }
        break;

    case 'POST':
        // Create new recipe
        $data = json_decode(file_get_contents('php://input'), true);
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO recipes (name, description, instructions, image_url, author_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['instructions'],
                $data['image_url'] ?? null,
                $data['author_id']
            ]);
            
            $recipe_id = $pdo->lastInsertId();
            
            // Add ingredients
            if (!empty($data['ingredients'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO recipe_ingredients (recipe_id, ingredient_id, amount, unit)
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($data['ingredients'] as $ingredient) {
                    $stmt->execute([
                        $recipe_id,
                        $ingredient['id'],
                        $ingredient['amount'] ?? null,
                        $ingredient['unit'] ?? null
                    ]);
                }
            }
            
            // Add categories
            if (!empty($data['categories'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO recipe_categories (recipe_id, category_id)
                    VALUES (?, ?)
                ");
                foreach ($data['categories'] as $category_id) {
                    $stmt->execute([$recipe_id, $category_id]);
                }
            }
            
            $pdo->commit();
            http_response_code(201);
            echo json_encode(['id' => $recipe_id]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        break;

    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Recipe ID required']);
            break;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                UPDATE recipes 
                SET name = ?, description = ?, instructions = ?, image_url = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['description'],
                $data['instructions'],
                $data['image_url'] ?? null,
                $id
            ]);
            
            // Update ingredients
            $pdo->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?")->execute([$id]);
            if (!empty($data['ingredients'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO recipe_ingredients (recipe_id, ingredient_id, amount, unit)
                    VALUES (?, ?, ?, ?)
                ");
                foreach ($data['ingredients'] as $ingredient) {
                    $stmt->execute([
                        $id,
                        $ingredient['id'],
                        $ingredient['amount'] ?? null,
                        $ingredient['unit'] ?? null
                    ]);
                }
            }
            
            // Update categories
            $pdo->prepare("DELETE FROM recipe_categories WHERE recipe_id = ?")->execute([$id]);
            if (!empty($data['categories'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO recipe_categories (recipe_id, category_id)
                    VALUES (?, ?)
                ");
                foreach ($data['categories'] as $category_id) {
                    $stmt->execute([$id, $category_id]);
                }
            }
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        break;

    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Recipe ID required']);
            break;
        }
        
        $pdo->beginTransaction();
        try {
            // Delete related records first
            $pdo->prepare("DELETE FROM recipe_ingredients WHERE recipe_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM recipe_categories WHERE recipe_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM recipe_reviews WHERE recipe_id = ?")->execute([$id]);
            
            // Delete the recipe
            $pdo->prepare("DELETE FROM recipes WHERE id = ?")->execute([$id]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        break;
} 