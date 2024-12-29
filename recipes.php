<?php
require_once 'db_connect.php';

// Определение переменных пагинации
$recipesPerPage = 51; // Количество рецептов на странице
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $recipesPerPage;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipe Collection</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .recipe-card {
            height: 100%;
            transition: transform 0.2s;
        }
        .recipe-card:hover {
            transform: translateY(-5px);
        }
        .recipe-image {
            height: 200px;
            object-fit: cover;
        }
        .ingredient-badge {
            margin: 2px;
        }
        .category-badge {
            margin: 2px;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <?php require_once 'header.php'; ?>

    <!-- Results Section -->
    <div class="row row-cols-1 row-cols-md-3 g-4" id="recipesContainer">
        <?php
        try {
            // Получаем общее количество рецептов
            $countQuery = "SELECT COUNT(DISTINCT r.id) as total FROM recipes r";
            $totalRecipes = $pdo->query($countQuery)->fetch(PDO::FETCH_ASSOC)['total'];
            $totalPages = ceil($totalRecipes / $recipesPerPage);

            // Основной запрос с пагинацией
            $query = "
                SELECT 
                    r.*, 
                    u.username as author_name,
                    GROUP_CONCAT(DISTINCT i.name) as ingredients,
                    GROUP_CONCAT(DISTINCT c.name) as categories,
                    GROUP_CONCAT(DISTINCT c.slug) as category_slugs,
                    COUNT(DISTINCT rv.id) as review_count,
                    AVG(rv.rating) as average_rating
                FROM recipes r
                LEFT JOIN users u ON r.author_id = u.id
                LEFT JOIN recipe_ingredients ri ON r.id = ri.recipe_id
                LEFT JOIN ingredients i ON ri.ingredient_id = i.id
                LEFT JOIN recipe_categories rc ON r.id = rc.recipe_id
                LEFT JOIN categories c ON rc.category_id = c.id
                LEFT JOIN recipe_reviews rv ON r.id = rv.recipe_id
                GROUP BY r.id
                ORDER BY r.created_at DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $pdo->prepare($query);
            $stmt->bindValue(':limit', $recipesPerPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            while ($recipe = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $ingredients = $recipe['ingredients'] ? explode(',', $recipe['ingredients']) : [];
                $categories = $recipe['categories'] ? explode(',', $recipe['categories']) : [];
                $rating = number_format($recipe['average_rating'], 1);
                ?>
                <div class="col">
                    <div class="card recipe-card">
                        <img src="<?= htmlspecialchars($recipe['image_url'] ?? 'images/default-recipe.jpg') ?>" 
                             class="card-img-top recipe-image" 
                             alt="<?= htmlspecialchars($recipe['name']) ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($recipe['name']) ?></h5>
                            <p class="card-text text-muted">
                                By <?= htmlspecialchars($recipe['author_name'] ?? 'Unknown') ?>
                            </p>
                            <p class="card-text">
                                <?= htmlspecialchars(substr($recipe['description'], 0, 100)) ?>...
                            </p>
                            
                            <!-- Rating -->
                            <div class="mb-2">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= $rating): ?>
                                        <i class="fas fa-star text-warning"></i>
                                    <?php else: ?>
                                        <i class="far fa-star text-warning"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <span class="ms-1">(<?= $recipe['review_count'] ?>)</span>
                            </div>

                            <!-- Categories -->
                            <div class="mb-2">
                                <?php foreach ($categories as $category): ?>
                                    <span class="badge bg-primary category-badge">
                                        <?= htmlspecialchars($category) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <!-- Ingredients -->
                            <div class="mb-2">
                                <?php foreach ($ingredients as $ingredient): ?>
                                    <span class="badge bg-secondary ingredient-badge">
                                        <?= htmlspecialchars($ingredient) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>


                        </div>
                        <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#recipeModal<?= $recipe['id'] ?>">
                                    View Details
                                </button>
                                <?php //if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $recipe['author_id']): ?>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editModal<?= $recipe['id'] ?>">
                                            Edit
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm delete-recipe" data-recipe-id="<?= $recipe['id'] ?>">
                                            Delete
                                        </button>
                                    </div>
                                <?php //endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recipe Details Modal -->
                    <div class="modal fade" id="recipeModal<?= $recipe['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><?= htmlspecialchars($recipe['name']) ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <img src="<?= htmlspecialchars($recipe['image_url'] ?? 'images/default-recipe.jpg') ?>" 
                                         class="img-fluid mb-3" 
                                         alt="<?= htmlspecialchars($recipe['name']) ?>">
                                    
                                    <h6>Description:</h6>
                                    <p><?= nl2br(htmlspecialchars($recipe['description'])) ?></p>
                                    
                                    <h6>Instructions:</h6>
                                    <p><?= nl2br(htmlspecialchars($recipe['instructions'])) ?></p>
                                    
                                    <h6>Ingredients:</h6>
                                    <div class="mb-3">
                                        <?php foreach ($ingredients as $ingredient): ?>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($ingredient) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <h6>Categories:</h6>
                                    <div class="mb-3">
                                        <?php foreach ($categories as $category): ?>
                                            <span class="badge bg-primary">
                                                <?= htmlspecialchars($category) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Modal -->
                    <?php //if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $recipe['author_id']): ?>
                       <!-- Edit Modal -->
<div class="modal fade" id="editModal<?= $recipe['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Recipe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form class="edit-recipe-form" data-recipe-id="<?= $recipe['id'] ?>">
                    <input type="hidden" name="recipe_id" value="<?= $recipe['id'] ?>">
                    
                    <!-- Existing fields -->
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" 
                               value="<?= htmlspecialchars($recipe['name']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3" 
                                  required><?= htmlspecialchars($recipe['description']) ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Instructions</label>
                        <textarea class="form-control" name="instructions" rows="5" 
                                  required><?= htmlspecialchars($recipe['instructions']) ?></textarea>
                    </div>

                    <!-- Categories Selection -->
                    <div class="mb-3">
                        <label class="form-label">Categories</label>
                        <select class="form-select" name="categories[]" multiple>
                            <?php
                            $recipeCategories = explode(',', $recipe['categories']);
                            $allCategories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
                            foreach ($allCategories as $category) {
                                $selected = in_array($category['name'], $recipeCategories) ? 'selected' : '';
                                echo "<option value='{$category['id']}' {$selected}>" . 
                                     htmlspecialchars($category['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Ingredients Selection -->
                    <div class="mb-3">
                        <label class="form-label">Ingredients</label>
                        <div id="ingredientsList<?= $recipe['id'] ?>">
                            <?php
                            $recipeIngredients = $pdo->prepare("
                                SELECT ri.*, i.name 
                                FROM recipe_ingredients ri 
                                JOIN ingredients i ON ri.ingredient_id = i.id 
                                WHERE ri.recipe_id = ?
                            ");
                            $recipeIngredients->execute([$recipe['id']]);
                            
                            while ($ing = $recipeIngredients->fetch()) {
                                ?>
                                <div class="input-group mb-2">
                                    <select class="form-select" name="ingredients[]">
                                        <?php
                                        $allIngredients = $pdo->query("SELECT * FROM ingredients ORDER BY name")->fetchAll();
                                        foreach ($allIngredients as $ingredient) {
                                            $selected = ($ingredient['id'] == $ing['ingredient_id']) ? 'selected' : '';
                                            echo "<option value='{$ingredient['id']}' {$selected}>" . 
                                                 htmlspecialchars($ingredient['name']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                    <input type="number" class="form-control" name="amounts[]" 
                                           value="<?= htmlspecialchars($ing['amount']) ?>" placeholder="Amount">
                                    <input type="text" class="form-control" name="units[]" 
                                           value="<?= htmlspecialchars($ing['unit']) ?>" placeholder="Unit">
                                    <button type="button" class="btn btn-danger" onclick="removeIngredient(this)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        <button type="button" class="btn btn-secondary btn-sm" 
                                onclick="addIngredient(<?= $recipe['id'] ?>)">
                            Add Ingredient
                        </button>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Image URL</label>
                        <input type="url" class="form-control" name="image_url" 
                               value="<?= htmlspecialchars($recipe['image_url'] ?? '') ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>
                    <?php // endif; ?>
                </div>
                <?php
            }
            ?>

            <!-- Pagination -->
             <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-center mt-4">
                    <nav aria-label="Recipe pagination">
                        <ul class="pagination">
                            <?php if ($currentPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $currentPage - 1 ?>">&laquo;</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $currentPage + 1 ?>">&raquo;</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
            </div>
            <?php
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>Error loading recipes: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        ?>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit Recipe Form Handler
    document.querySelectorAll('.edit-recipe-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const modalElement = this.closest('.modal');
            const modal = bootstrap.Modal.getInstance(modalElement);

            try {
                submitButton.disabled = true;
                submitButton.innerHTML = 'Saving...';

                const response = await fetch('update_recipe.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    alert(result.message);
                    modal.hide();
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            } finally {
                submitButton.disabled = false;
                submitButton.innerHTML = 'Save Changes';
            }
        });
    });

    // Delete Recipe Handler
    document.querySelectorAll('.delete-recipe').forEach(button => {
        button.addEventListener('click', async function() {
            if (confirm('Are you sure you want to delete this recipe?')) {
                const recipeId = this.dataset.recipeId;
                try {
                    const response = await fetch('delete_recipe.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ recipe_id: recipeId })
                    });
                    
                    const result = await response.json();
                    
                    if (result.status === 'success') {
                        alert('Recipe deleted successfully');
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                } catch (error) {
                    alert('Error: ' + error.message);
                }
            }
        });
    });
});


// Add to your existing JavaScript
function addIngredient(recipeId) {
    const ingredientsList = document.getElementById(`ingredientsList${recipeId}`);
    const ingredientTemplate = `
        <div class="input-group mb-2">
            <select class="form-select" name="ingredients[]">
                <?php
                $allIngredients = $pdo->query("SELECT * FROM ingredients ORDER BY name")->fetchAll();
                foreach ($allIngredients as $ingredient) {
                    echo "<option value='{$ingredient['id']}'>" . 
                         htmlspecialchars($ingredient['name']) . "</option>";
                }
                ?>
            </select>
            <input type="number" class="form-control" name="amounts[]" placeholder="Amount">
            <input type="text" class="form-control" name="units[]" placeholder="Unit">
            <button type="button" class="btn btn-danger" onclick="removeIngredient(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    ingredientsList.insertAdjacentHTML('beforeend', ingredientTemplate);
}

function removeIngredient(button) {
    button.closest('.input-group').remove();
}

// Update your existing form submission handler
document.querySelectorAll('.edit-recipe-form').forEach(form => {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        // Convert multiple select values to array
        const categories = Array.from(this.querySelector('select[name="categories[]"]').selectedOptions)
            .map(option => option.value);
        formData.delete('categories[]');
        categories.forEach(cat => formData.append('categories[]', cat));

        // The rest of your existing submission code...
    });
});

</script>

</body>
</html>