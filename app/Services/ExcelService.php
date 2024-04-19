<?php

namespace App\Services;

use App\Imports\ExcelImport;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ExcelService
{
    public function getData(string $filePath): ?array
    {
        try {
            ini_set('memory_limit', '-1');
            return Excel::toArray(new ExcelImport(), $filePath)[0];
        } catch (Exception $e) {
            Log::error('Error importing Excel file: ' . $e->getMessage());
            return NULL;
        }
    }

    public function getCategories(string $filePath): ?array
    {
        $categories = [];
        $data = $this->getData($filePath);

        foreach ($data as $item) {
            $categories[] = $item['category'];
        }

        return array_unique($categories);
    }

    public function getDataOfCategory(string $filePath, string $category): array
    {
        $data = $this->getData($filePath);
        $dataOfCategory = [];

        foreach ($data as $item) {
            if ($item['category'] === $category) {
                $dataOfCategory[] = $item;
            }
        }

        return $dataOfCategory;
    }

    public function checkForXlsxFormat(string $filePath): bool
    {
        return $filePath instanceof UploadedFile && $filePath->getClientOriginalExtension() === 'xlsx';
    }
}
