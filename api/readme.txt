RECIPE API DOCUMENTATION
=======================

Base URL: /api/

ENDPOINTS
---------

1. RECIPES
   GET    /api/recipes          - Get all recipes (with filters)
   GET    /api/recipes/{id}     - Get single recipe
   POST   /api/recipes          - Create new recipe
   PUT    /api/recipes/{id}     - Update recipe
   DELETE /api/recipes/{id}     - Delete recipe

2. CATEGORIES
   GET    /api/categories       - Get all categories
   GET    /api/categories/{id}  - Get single category

3. INGREDIENTS
   GET    /api/ingredients      - Get all ingredients
   GET    /api/ingredients/{id} - Get single ingredient

QUERY PARAMETERS
---------------
page        : Page number (default: 1)
per_page    : Items per page (default: 12, max: 50)
search      : Search term in recipe name/description
category    : Filter by category ID
ingredient  : Filter by ingredient ID
min_rating  : Filter by minimum rating (1-5)

EXAMPLE REQUESTS
--------------
1. Get all recipes:
   GET /api/recipes?page=1&per_page=12

2. Search recipes:
   GET /api/recipes?search=chicken&category=1

3. Create recipe:
   POST /api/recipes
   {
     "name": "Recipe Name",
     "description": "Description",
     "instructions": "Instructions",
     "image_url": "https://...",
     "ingredients": [
       {"id": 1, "amount": 100, "unit": "g"}
     ],
     "categories": [1, 2]
   }

4. Update recipe:
   PUT /api/recipes/123
   {
     "name": "Updated Name",
     "description": "New description"
   }

RESPONSE FORMAT
-------------
Success:
{
    "data": [...],
    "pagination": {
        "total": 100,
        "per_page": 12,
        "current_page": 1,
        "last_page": 9
    }
}

Error:
{
    "error": "Error message"
}

STATUS CODES
-----------
200 : OK
201 : Created
400 : Bad Request
401 : Unauthorized
404 : Not Found
500 : Server Error

For detailed documentation visit: your-domain.com/api/docs