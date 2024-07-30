<?php

namespace App\Services;

use App\Imports\ExcelImport;
use Exception;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ExcelService extends BaseWorkService
{
    public function __construct(protected ApiClientService $apiClientService) { }

    /**
     * @param string $filePath
     * @return array|null
     */
    public function getData(string $filePath): ?array
    {
        try {
            return Excel::toArray(new ExcelImport(), $filePath)[0];
        } catch (Exception $e) {
            Log::error('Error importing Excel file: ' . $e->getMessage());
            return NULL;
        }
    }

    /**
     * @param array $data
     * @return array|null
     */
    public function fetchSheetLinkExcel(array $data): ?array
    {
        try {
            $hashes = $this->generateHashes($data);
            return $this->apiClientService->getAvitoCategories($hashes);
        } catch (\Throwable $e) {
            Log::error('Error fetching sheet links: ' . $e->getMessage());
            return NULL;
        }
    }

    /**
     * @param string $filePath
     * @return bool
     */
    public function checkForXlsxFormat(string $filePath): bool
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        return strtolower($extension) === 'xlsx';
    }
}
