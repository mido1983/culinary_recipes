<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Preview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <?php require_once 'header.php'; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form id="previewForm">
                <div class="mb-3">
                    <label for="api_url" class="form-label">API URL</label>
                    <input type="text" id="api_url" class="form-control" 
                           placeholder="Enter API URL (e.g., https://www.themealdb.com/api/json/v1/1/search.php?s=Arrabiata)">
                </div>
                <button type="submit" class="btn btn-primary">Preview API Data</button>
            </form>
        </div>
    </div>

    <div id="previewResult" class="d-none">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">API Response Preview</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Raw Data:</h6>
                        <pre id="rawData" class="bg-light p-3 rounded"></pre>
                    </div>
                    <div class="col-md-6">
                        <h6>Formatted Preview:</h6>
                        <div id="formattedPreview"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('previewForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const apiUrl = document.getElementById('api_url').value;
    const previewResult = document.getElementById('previewResult');
    const rawData = document.getElementById('rawData');
    const formattedPreview = document.getElementById('formattedPreview');

    try {
        const response = await fetch('api_preview.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ api_url: apiUrl })
        });

        const data = await response.json();
        
        if (data.status === 'error') {
            throw new Error(data.message);
        }

        // Показываем raw data
        rawData.textContent = JSON.stringify(data.data, null, 2);

        // Создаем форматированный предпросмотр
        formattedPreview.innerHTML = '';
        if (data.data.meals) {
            data.data.meals.forEach(meal => {
                const card = document.createElement('div');
                card.className = 'card mb-3';
                card.innerHTML = `
                    <div class="row g-0">
                        <div class="col-md-4">
                            <img src="${meal.strMealThumb}" class="img-fluid rounded-start" alt="${meal.strMeal}">
                        </div>
                        <div class="col-md-8">
                            <div class="card-body">
                                <h5 class="card-title">${meal.strMeal}</h5>
                                <p class="card-text">Category: ${meal.strCategory}</p>
                                <p class="card-text"><small class="text-muted">Instructions available</small></p>
                            </div>
                        </div>
                    </div>
                `;
                formattedPreview.appendChild(card);
            });
        }

        previewResult.classList.remove('d-none');
    } catch (error) {
        alert('Error: ' + error.message);
    }
});
</script>
</body>
</html> 