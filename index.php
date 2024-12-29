<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recipe Collection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .draggable {
            padding: 10px;
            margin: 5px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            cursor: move;
            display: inline-block;
        }

        .droppable {
            padding: 15px;
            margin: 10px 0;
            border: 2px dashed #dee2e6;
            border-radius: 4px;
            min-height: 60px;
        }

        .droppable.hover {
            background-color: #e9ecef;
            border-color: #6c757d;
        }

        .mapped-field {
            min-height: 30px;
        }

        .loading {
            opacity: 0.5;
            pointer-events: none;
        }

        #apiFields {
            min-height: 100px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <nav>
            <div class="nav nav-tabs" id="nav-tab" role="tablist">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#recipes" type="button">Recipe Collection</button>
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#import" type="button">Import Recipes</button>
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#preview" type="button">Preview API Data</button>
            </div>
        </nav>

        <div class="tab-content mt-3">
            <div class="tab-pane fade show active" id="recipes">
                <h2>Recipe Collection</h2>
                <!-- Recipe list will be here -->
            </div>

            <div class="tab-pane fade" id="import">
                <h2>Import Recipes</h2>
                <form id="apiForm" class="mb-4">
                    <div class="mb-3">
                        <label for="api_url" class="form-label">API URL</label>
                        <input type="url" class="form-control" id="api_url" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Fetch API Structure</button>
                </form>

                <div id="feedback" class="alert d-none"></div>

                <div id="mappingSection" class="d-none">
                    <h3>Map API Fields</h3>
                    <p>Drag fields from the API response to map them to recipe fields</p>

                    <div id="apiFields" class="mb-4">
                        <!-- API fields will be added here -->
                    </div>

                    <div class="mapping-container">
                        <div class="droppable" data-field="name">
                            <strong>Recipe Name</strong>
                            <div class="mapped-field"></div>
                        </div>
                        <div class="droppable" data-field="description">
                            <strong>Description</strong>
                            <div class="mapped-field"></div>
                        </div>
                        <div class="droppable" data-field="instructions">
                            <strong>Instructions</strong>
                            <div class="mapped-field"></div>
                        </div>
                        <div class="droppable" data-field="image_url">
                            <strong>Image URL</strong>
                            <div class="mapped-field"></div>
                        </div>
                        <div class="droppable" data-field="ingredients">
                            <strong>Ingredients</strong>
                            <div class="mapped-field"></div>
                        </div>
                        <div class="droppable" data-field="categories">
                            <strong>Categories</strong>
                            <div class="mapped-field"></div>
                        </div>
                    </div>

                    <button id="confirmMapping" class="btn btn-success mt-3">Import Recipes</button>
                </div>
            </div>

            <div class="tab-pane fade" id="preview">
                <h2>Preview API Data</h2>
                <!-- Preview content will be here -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let dragged = null;
            const droppables = document.querySelectorAll('.droppable');
            
            // Drag and Drop handlers
            document.addEventListener('dragstart', function(e) {
                if (e.target.classList.contains('draggable')) {
                    dragged = e.target;
                    e.target.style.opacity = '0.5';
                }
            });

            document.addEventListener('dragend', function(e) {
                if (e.target.classList.contains('draggable')) {
                    e.target.style.opacity = '';
                }
            });

            droppables.forEach(droppable => {
                droppable.addEventListener('dragover', e => {
                    e.preventDefault();
                    droppable.classList.add('hover');
                });

                droppable.addEventListener('dragleave', e => {
                    droppable.classList.remove('hover');
                });

                droppable.addEventListener('drop', e => {
                    e.preventDefault();
                    droppable.classList.remove('hover');
                    if (dragged) {
                        const mappedField = droppable.querySelector('.mapped-field');
                        const newField = dragged.cloneNode(true);
                        
                        const deleteBtn = document.createElement('button');
                        deleteBtn.className = 'btn btn-sm btn-danger ms-2';
                        deleteBtn.innerHTML = '×';
                        deleteBtn.onclick = e => {
                            e.stopPropagation();
                            mappedField.innerHTML = '';
                        };
                        
                        newField.appendChild(deleteBtn);
                        mappedField.innerHTML = '';
                        mappedField.appendChild(newField);
                    }
                });
            });

            // API Form Handler
            document.getElementById('apiForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const form = this;
                const feedback = document.getElementById('feedback');
                const mappingSection = document.getElementById('mappingSection');
                const apiFields = document.getElementById('apiFields');
                
                form.classList.add('loading');
                feedback.classList.add('d-none');

                const formData = new FormData();
                formData.append('action', 'fetch_example');
                formData.append('api_url', document.getElementById('api_url').value);

                fetch('culinary_recipes.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'error') {
                        throw new Error(data.message);
                    }

                    apiFields.innerHTML = '';
                    
                    if (!data.example) {
                        throw new Error('No example data received');
                    }

                    Object.entries(data.example).forEach(([key, value]) => {
                        const field = document.createElement('div');
                        field.className = 'draggable';
                        field.draggable = true;
                        field.textContent = key;
                        apiFields.appendChild(field);
                    });

                    mappingSection.classList.remove('d-none');
                })
                .catch(error => {
                    feedback.textContent = error.message;
                    feedback.classList.remove('d-none', 'alert-success');
                    feedback.classList.add('alert-danger');
                })
                .finally(() => {
                    form.classList.remove('loading');
                });
            });

            // Confirm Mapping Handler
            document.getElementById('confirmMapping').addEventListener('click', function() {
                const feedback = document.getElementById('feedback');
                const button = this;
                const mapping = {};

                document.querySelectorAll('.droppable').forEach(droppable => {
                    const field = droppable.getAttribute('data-field');
                    const mappedField = droppable.querySelector('.draggable');
                    if (mappedField) {
                        mapping[field] = mappedField.textContent.replace('×', '').trim();
                    }
                });

                button.disabled = true;
                button.classList.add('loading');
                feedback.classList.add('d-none');

                const formData = new FormData();
                formData.append('action', 'process_mapping');
                formData.append('api_url', document.getElementById('api_url').value);
                formData.append('mapping', JSON.stringify(mapping));

                fetch('culinary_recipes.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    feedback.textContent = data.message;
                    feedback.classList.remove('d-none');
                    feedback.classList.add(
                        data.status === 'success' ? 'alert-success' : 'alert-danger'
                    );
                })
                .catch(error => {
                    feedback.textContent = 'An error occurred: ' + error.message;
                    feedback.classList.remove('d-none', 'alert-success');
                    feedback.classList.add('alert-danger');
                })
                .finally(() => {
                    button.disabled = false;
                    button.classList.remove('loading');
                });
            });

            // API Fields drop handler
            document.getElementById('apiFields').addEventListener('dragover', e => {
                e.preventDefault();
            });

            document.getElementById('apiFields').addEventListener('drop', e => {
                e.preventDefault();
                if (dragged && dragged.parentElement.classList.contains('mapped-field')) {
                    dragged.parentElement.innerHTML = '';
                }
            });
        });
    </script>
</body>
</html>
