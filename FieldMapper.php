<?php
class FieldMapper {
    private $internalFields = [
        'name' => ['name', 'title', 'recipe_name'],
        'description' => ['description', 'summary', 'about'],
        'instructions' => ['instructions', 'steps', 'method', 'directions'],
        'image_url' => ['image', 'photo', 'picture', 'thumbnail'],
        'ingredients' => ['ingredients', 'ingredient_list'],
        'categories' => ['categories', 'category', 'tags']
    ];

    public function calculateSimilarity($str1, $str2) {
        $str1 = strtolower($str1);
        $str2 = strtolower($str2);
        
        // Используем расстояние Левенштейна
        $levenshtein = levenshtein($str1, $str2);
        $maxLength = max(strlen($str1), strlen($str2));
        
        // Преобразуем в процент схожести
        return (1 - $levenshtein / $maxLength) * 100;
    }

    public function suggestMapping($apiFields) {
        $suggestions = [];
        
        foreach ($this->internalFields as $internalField => $possibleMatches) {
            $bestMatch = null;
            $bestScore = 0;
            
            foreach ($apiFields as $apiField) {
                // Проверяем точное совпадение
                if (in_array($apiField, $possibleMatches)) {
                    $bestMatch = $apiField;
                    $bestScore = 100;
                    break;
                }
                
                // Проверяем схожесть
                foreach ($possibleMatches as $possibleMatch) {
                    $score = $this->calculateSimilarity($apiField, $possibleMatch);
                    if ($score > $bestScore && $score > 70) {
                        $bestMatch = $apiField;
                        $bestScore = $score;
                    }
                }
            }
            
            if ($bestMatch) {
                $suggestions[$internalField] = [
                    'field' => $bestMatch,
                    'confidence' => $bestScore
                ];
            }
        }
        
        return $suggestions;
    }
} 