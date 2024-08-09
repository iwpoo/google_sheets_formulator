<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;

class BaseWorkService
{
    /**
     * @param array $data
     * @param array $allCategories
     * @return string
     */
    protected function getTreePathByCategories(array $data, array $allCategories): string
    {
        $categoriesById = collect($allCategories['data'])->keyBy('remote_id');

        $findParentCategories = static function ($categoryId) use ($categoriesById, &$findParentCategories): array {
            $parentCategories = [];

            $currentCategory = $categoriesById[$categoryId] ?? NULL;

            if ($currentCategory) {
                $parentCategories[] = $currentCategory['name'];

                $parentId = $currentCategory['parent'];
                if ($parentId != 0) {
                    $parentCategories = array_merge($findParentCategories($parentId), $parentCategories);
                }
            }

            return $parentCategories;
        };

        $allPathCategories = [];

        foreach ($data as $category) {
            $categoryId = $this->findClosestCategoryId($category, $categoriesById);

            if ($categoryId !== NULL) {
                $parentCategories = $findParentCategories($categoryId);
                $allPathCategories[] = array_merge($parentCategories, [$categoriesById[$categoryId]['name']]);
            }
        }

        return implode('', array_unique(array_merge(...$allPathCategories)));
    }

    /**
     * @param string $category
     * @param $categoriesById
     * @return int|null
     */
    private function findClosestCategoryId(string $category, $categoriesById): ?int
    {
        $closestCategoryId = NULL;
        $shortestDistance = NULL;

        foreach ($categoriesById as $id => $categoryData) {
            $distance = levenshtein(mb_strtolower($category), mb_strtolower($categoryData['name']));

            if ($shortestDistance === NULL || $distance < $shortestDistance) {
                $closestCategoryId = $id;
                $shortestDistance = $distance;
            }
        }

        // Возвращаем ID только если расстояние достаточно маленькое (например, меньше 3)
        return $shortestDistance !== NULL && $shortestDistance < 3 ? $closestCategoryId : NULL;
    }

    /**
     * @param array $data
     * @return array
     */
    protected function getTreePathCategories(array $data = []): array
    {
        $pathCategories = [];

        $data = array_map('array_change_key_case', $data);
        foreach ($data as $item) {
            $typeKeys = array_filter(array_keys($item), static function (string $key): bool {
                return str_contains($key, 'type') && !str_contains($key, 'adtype');
            });

            $categoriesTypes = '';

            if (count($typeKeys) > 0) {
                rsort($typeKeys);

                foreach ($typeKeys as $typeKey) {
                    if (is_array($item[$typeKey])) {
                        continue;
                    }
                    $categoriesTypes .= '/' . $item[$typeKey];
                }
            }

            $pathCategories[] = $item['category'] . $categoriesTypes;
        }

        return array_unique($pathCategories);
    }

    /**
     * @param array $data
     * @return array
     * @throws RequestException
     */
    protected function generateHashes(array $data): array
    {
        $hashes = [];
        $fullTreePaths = [];

        $treePaths = $this->getTreePathCategories($data);
        $categories = $this->apiClientService->getParserAvitoCategories();

        foreach ($treePaths as $treePath) {
            $treePath = explode('/', $treePath);
            $fullTreePaths[] = $this->getTreePathByCategories($treePath, $categories);
        }

        foreach (array_unique($fullTreePaths) as $fullTreePath) {
            $hashes[] = md5($fullTreePath);
        }

        return $hashes;
    }

    /**
     * @param array $treePath
     * @param array $data
     * @return array
     */
    public function getDataOfCategory(array $treePath, array $data = []): array
    {
        $dataOfCategory = [];

        $data = array_map('array_change_key_case', $data);

        foreach ($data as $item) {
            $foundAll = FALSE;
            foreach ($treePath as $category) {
                similar_text(strtolower($item['category']), strtolower($category), $similarity);
                if ($similarity > 80) {
                    $foundAll = TRUE;
                    break;
                }
            }

            if ($foundAll) {
                $dataOfCategory[] = $item;
            }
        }

        return $dataOfCategory;
    }
}
