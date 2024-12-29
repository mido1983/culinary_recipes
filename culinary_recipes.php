<?php
require_once 'db_connect.php'; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die(json_encode(["status" => "error", "message" => "Database connection failed: " . $e->getMessage()]));
}
file_put_contents('debug.log', json_encode($_POST), FILE_APPEND);

// Функция для проверки API и получения примерной структуры
function fetchApiExample($apiUrl) {
    error_log("API Response: " . $apiUrl);
    if (!filter_var($apiUrl, FILTER_VALIDATE_URL)) {
        return ["status" => "error", "message" => "Invalid URL format."];
    }

    $response = @file_get_contents($apiUrl);
    error_log("API Response: " . $response);
    
    if ($response === false) {
        return ["status" => "error", "message" => "Failed to fetch data from API."];
    }

    $data = json_decode($response, true);
    error_log("Decoded data: " . print_r($data, true));
    
    if (!$data || !isset($data['meals']) || !is_array($data['meals'])) {
        return ["status" => "error", "message" => "Invalid API response."];
    }

    // Получаем первый рецепт
    $recipe = $data['meals'][0];
    
    // Преобразуем структуру в нужный формат
    $example = [
        'name' => $recipe['strMeal'],
        'description' => $recipe['strCategory'],
        'instructions' => $recipe['strInstructions'],
        'image_url' => $recipe['strMealThumb']
    ];
    
    // Собираем все ингредиенты
    $ingredients = [];
    for ($i = 1; $i <= 20; $i++) {
        $ingredient = $recipe["strIngredient$i"];
        $measure = $recipe["strMeasure$i"];
        
        if (!empty($ingredient)) {
            $ingredients[] = [
                'name' => $ingredient,
                'amount' => $measure ?: null
            ];
        }
    }
    $example['ingredients'] = $ingredients;
    
    // Добавляем категории
    $example['categories'] = [$recipe['strCategory']];

    // Создаем экземпляр маппера и получаем предложения
    require_once 'FieldMapper.php';
    $mapper = new FieldMapper();
    $suggestions = $mapper->suggestMapping(array_keys($example));

    $result = [
        "status" => "success",
        "example" => $example,
        "suggestions" => $suggestions
    ];
    
    error_log("Result: " . print_r($result, true));
    
    return $result;
}

// Функция для сопоставления единиц измерения
function matchMeasurementUnit($unitName, $pdo) {
    // Очищаем и нормализуем название единицы
    $unitName = trim(strtolower($unitName));
    
    // Массив соответствий из API к нашей БД
    $unitMappings = [
        // Объем
        'cups' => 'cup',
        'cup' => 'cup',
        'tablespoons' => 'tbsp',
        'tablespoon' => 'tbsp',
        'tbsp' => 'tbsp',
        'tbs' => 'tbsp',
        'teaspoons' => 'tsp',
        'teaspoon' => 'tsp',
        'tsp' => 'tsp',
        'ml' => 'ml',
        'milliliters' => 'ml',
        'l' => 'l',
        'liters' => 'l',
        'oz' => 'oz',
        'ounce' => 'oz',
        'ounces' => 'oz',
        'fl oz' => 'fl oz',
        
        // Вес
        'g' => 'g',
        'gram' => 'g',
        'grams' => 'g',
        'kg' => 'kg',
        'kilogram' => 'kg',
        'kilograms' => 'kg',
        'pound' => 'lb',
        'pounds' => 'lb',
        'lb' => 'lb',
        'lbs' => 'lb',
        
        // Количество
        'piece' => 'pc',
        'pieces' => 'pc',
        'pc' => 'pc',
        'pcs' => 'pc',
        'whole' => 'whole',
        'pinch' => 'pinch',
        'pinches' => 'pinch',
        'dash' => 'dash',
        'dashes' => 'dash',
        'handful' => 'handful',
        'handfuls' => 'handful',
        'slice' => 'slice',
        'slices' => 'slice',
        'clove' => 'clove',
        'cloves' => 'clove',
        'package' => 'pkg',
        'packages' => 'pkg',
        'pkg' => 'pkg',
        'can' => 'can',
        'cans' => 'can',
        'bunch' => 'bunch',
        'bunches' => 'bunch',
        'sprig' => 'sprig',
        'sprigs' => 'sprig'
    ];

    // Если есть прямое соответствие
    if (isset($unitMappings[$unitName])) {
        $mappedUnit = $unitMappings[$unitName];
        
        $stmt = $pdo->prepare("
            SELECT id FROM measurement_units 
            WHERE name = ? OR abbreviation = ?
        ");
        $stmt->execute([$mappedUnit, $mappedUnit]);
        $unitId = $stmt->fetchColumn();
        
        if ($unitId) {
            return $unitId;
        }
    }

    // Если не нашли соответствие, возвращаем null
    return null;
}

// Функция для обработки данных с использованием маппинга
function processMappedData($apiUrl, $mapping, $pdo) {
    try {
        $pdo->beginTransaction();
        
        error_log("Starting import with URL: " . $apiUrl);
        error_log("Mapping: " . print_r($mapping, true));

        $response = @file_get_contents($apiUrl);
        if ($response === false) {
            throw new Exception("Failed to fetch data from API");
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['meals']) || !is_array($data['meals'])) {
            throw new Exception("Invalid API response structure");
        }

        foreach ($data['meals'] as $meal) {
            error_log("Processing meal: " . print_r($meal, true));

            // Получаем данные из API
            $name = $meal['strMeal'] ?? null;
            $description = $meal['strCategory'] ?? null;
            $instructions = $meal['strInstructions'] ?? null;
            $image_url = $meal['strMealThumb'] ?? null;

            if (empty($name)) {
                throw new Exception("Recipe name is required");
            }

            // Создаем slug
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));

            // Проверяем существование рецепта
            $stmt = $pdo->prepare("SELECT id FROM `recipes` WHERE `slug` = ?");
            $stmt->execute([$slug]);
            $existingRecipeId = $stmt->fetchColumn();

            if ($existingRecipeId) {
                // Если рецепт существует, удаляем старые связи
                $stmt = $pdo->prepare("DELETE FROM `recipe_ingredients` WHERE `recipe_id` = ?");
                $stmt->execute([$existingRecipeId]);
                
                $stmt = $pdo->prepare("DELETE FROM `recipe_categories` WHERE `recipe_id` = ?");
                $stmt->execute([$existingRecipeId]);

                // Обновляем существующий рецепт
                $stmt = $pdo->prepare("
                    UPDATE `recipes` 
                    SET `name` = ?, `description` = ?, `instructions` = ?, `image_url` = ?
                    WHERE `id` = ?
                ");
                $stmt->execute([$name, $description, $instructions, $image_url, $existingRecipeId]);
                
                $recipeId = $existingRecipeId;
            } else {
                // Создаем новый рецепт
                $stmt = $pdo->prepare("
                    INSERT INTO `recipes` 
                    (`name`, `slug`, `description`, `instructions`, `image_url`, `status`) 
                    VALUES (?, ?, ?, ?, ?, 'published')
                ");
                $stmt->execute([$name, $slug, $description, $instructions, $image_url]);
                $recipeId = $pdo->lastInsertId();
            }

            // Обработка ингредиентов
            for ($i = 1; $i <= 20; $i++) {
                $ingredientName = $meal["strIngredient$i"] ?? null;
                $measure = $meal["strMeasure$i"] ?? null;

                if (!empty($ingredientName)) {
                    // Создаем или получаем ингредиент
                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO `ingredients` (`name`) 
                        VALUES (?)
                    ");
                    $stmt->execute([trim($ingredientName)]);

                    $stmt = $pdo->prepare("
                        SELECT `id` FROM `ingredients` WHERE `name` = ?
                    ");
                    $stmt->execute([trim($ingredientName)]);
                    $ingredientId = $stmt->fetchColumn();

                    if ($ingredientId && !empty($measure)) {
                        $measure = trim($measure);
                        $amount = null;
                        $unitId = null;

                        if (preg_match('/^([\d.\/]+)\s*(.*)$/', $measure, $matches)) {
                            $amount = $matches[1];
                            $unitName = trim($matches[2]);

                            if (!empty($unitName)) {
                                $unitId = matchMeasurementUnit($unitName, $pdo);
                            }
                        }

                        // Используем INSERT IGNORE для предотвращения дубликатов
                        $stmt = $pdo->prepare("
                            INSERT IGNORE INTO `recipe_ingredients` 
                            (`recipe_id`, `ingredient_id`, `amount`, `unit_id`) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$recipeId, $ingredientId, $amount, $unitId]);
                    }
                }
            }

            // Обработка категории
            if (!empty($description)) {
                $categorySlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $description)));
                
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO `categories` (`name`, `slug`) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$description, $categorySlug]);

                $stmt = $pdo->prepare("
                    SELECT `id` FROM `categories` WHERE `name` = ?
                ");
                $stmt->execute([$description]);
                $categoryId = $stmt->fetchColumn();

                if ($categoryId) {
                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO `recipe_categories` 
                        (`recipe_id`, `category_id`) 
                        VALUES (?, ?)
                    ");
                    $stmt->execute([$recipeId, $categoryId]);
                }
            }
        }

        $pdo->commit();
        return ["status" => "success", "message" => "Recipes imported successfully!"];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Import Error: " . $e->getMessage());
        error_log("Last SQL Error: " . print_r($pdo->errorInfo(), true));
        return ["status" => "error", "message" => "Import failed: " . $e->getMessage()];
    }
}

function validateApiResponse($data) {
    if (!is_array($data)) {
        return false;
    }
    
    // Add more specific validation based on your needs
    return true;
}

// Обработка POST-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    if ($action === 'fetch_example') {
        file_put_contents('debug.log', "fetch_example matched\n", FILE_APPEND);
        $apiUrl = $_POST['api_url'] ?? null;
        echo json_encode(fetchApiExample($apiUrl));
        exit;
    }

    elseif ($action === 'process_mapping') {
        $apiUrl = $_POST['api_url'] ?? null;
        $mapping = json_decode($_POST['mapping'] ?? '{}', true);
        echo json_encode(processMappedData($apiUrl, $mapping, $pdo));
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid action."]);
    }
    exit;
}
?>
