<?php

namespace App\Services;

use Exception;
use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Google_Service_Sheets;
use GuzzleHttp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GoogleSheetService
{
    public function __construct(
        protected ApiClientService $apiClientService
    ) {}

    public function insertData(string $spreadsheetId, string $sheetName, array $data = []): bool
    {
        $client = new Client();
        $dataAccountJson = $this->apiClientService->getDataServiceAccount();
        $uniqueId = Str::uuid();
        Storage::put("credentials/google_credentials_$uniqueId.json", $dataAccountJson);
        $dataJson = storage_path("app/credentials/google_credentials_$uniqueId.json");
        $client->setAuthConfig($dataJson);
        $client->addScope(Google_Service_Sheets::SPREADSHEETS);
        $client->setHttpClient(new GuzzleHttp\Client(['verify' => false]));

        try {
            $service = new Sheets($client);
            $data = array_map('array_change_key_case', $data);

            $response = $service->spreadsheets_values->get($spreadsheetId, "$sheetName!2:2");
            $headerRow = $response->getValues()[0];

            $columnValues = [];
            $idColumnIndex = array_search('id', array_map('strtolower', $headerRow));

            $currentData = $service->spreadsheets_values->get($spreadsheetId, "$sheetName!" . $this->getColumnLetter($idColumnIndex + 1) . '3:' . $this->getColumnLetter($idColumnIndex + 1) . (count($data) + 2))->getValues();
            $existingIds = [];

            if (!empty($currentData)) {
                foreach ($currentData as $rowData) {
                    if (isset($rowData[0])) {
                        $existingIds[] = $rowData[0];
                    }
                }
            }

            usort($data, function ($a, $b) {
                $dateA = isset($a['datebegin']) ? strtotime($a['datebegin']) : 0;
                $dateB = isset($b['datebegin']) ? strtotime($b['datebegin']) : 0;
                return $dateA - $dateB;
            });

            foreach ($data as $item) {
                if (isset($item['id']) && in_array($item['id'], $existingIds)) {
                    continue;
                }

                $item['datebegin'] = 'сразу';

                if (isset($item['images'])) {
                    $imageUrls = [];
                    foreach ($item['images'] as $image) {
                        $imageArray = (array)$image;
                        $imageUrls[] = $imageArray['@attributes']['url'];
                    }
                    $item['images'] = implode("\n", $imageUrls);
                } elseif (isset($item['imageurls'])) {
                    $imageUrls = explode("|", $item['imageurls']);
                    $item['images'] = implode("\n", $imageUrls);
                }

                foreach ($item as $key => $value) {
                    $columnLetter = $this->searchColumnLetter($key, $headerRow);
                    if ($columnLetter !== NULL) {
                        $rowIndex = count($existingIds) + 3;
                        $range = "$columnLetter$rowIndex";
                        $columnValues[$range][] = [$value ?? NULL];
                    }
                }
            }

            foreach ($columnValues as $range => $values) {
                $service->spreadsheets_values->update($spreadsheetId, "$sheetName!$range", new ValueRange(['values' => $values]), ['valueInputOption' => 'RAW']);
            }
            Storage::delete("credentials/google_credentials_$uniqueId.json");
            return TRUE;
        } catch (Exception $e) {
            Storage::delete("credentials/google_credentials_$uniqueId.json");
            Log::error($e->getMessage());
            return FALSE;
        }
    }

//    private function createNewSheet($service, $spreadsheetId, $baseSheetName)
//    {
//        $response = $service->spreadsheets->get($spreadsheetId);
//        $sheets = $response->getSheets();
//        $lastSheet = end($sheets);
//        $lastSheetIndex = $lastSheet->getProperties()->getIndex();
//        $newSheetName = $baseSheetName . ' #' . ($lastSheetIndex + 1);
//        $requests = [
//            new \Google\Service\Sheets\Request([
//                'addSheet' => [
//                    'properties' => [
//                        'title' => $newSheetName
//                    ]
//                ]
//            ])
//        ];
//        $batchUpdateRequest = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
//            'requests' => $requests
//        ]);
//        $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
//        return $newSheetName;
//    }
//
//    private function copyDataToNewSheet($service, $spreadsheetId, $newSheetName)
//    {
//        $response = $service->spreadsheets->get($spreadsheetId);
//        $sheets = $response->getSheets();
//        $firstSheet = $sheets[0];
//        $firstSheetName = $firstSheet->getProperties()->getTitle();
//        $range = $firstSheetName . '!A1:ZZ2';
//        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
//        $values = $response->getValues();
//        $targetRange = $newSheetName . '!A1:ZZ2';
//        $body = new \Google\Service\Sheets\ValueRange(['values' => $values]);
//        $params = ['valueInputOption' => 'RAW'];
//        $service->spreadsheets_values->update($spreadsheetId, $targetRange, $body, $params);
//    }
//    private function clearDataOnFirstSheet($service, $spreadsheetId)
//    {
//        $response = $service->spreadsheets->get($spreadsheetId);
//        $sheets = $response->getSheets();
//        $firstSheet = $sheets[0];
//        $firstSheetName = $firstSheet->getProperties()->getTitle();
//        $range = $firstSheetName . '!A3:ZZ';
//        $values = [];
//        $body = new \Google\Service\Sheets\ValueRange(['values' => $values]);
//        $params = ['valueInputOption' => 'RAW'];
//        $service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
//    }

    private function getColumnLetter($columnNumber): string
    {
        $temp = '';
        while ($columnNumber > 0) {
            $modulo = ($columnNumber - 1) % 26;
            $temp = chr(65 + $modulo) . $temp;
            $columnNumber = (int)(($columnNumber - $modulo) / 26);
        }
        return $temp;
    }

    private function searchColumnLetter($fieldName, $headerRow): ?string
    {
        $lowercaseFieldName = strtolower($fieldName);
        $lowercaseHeaderRow = array_map('strtolower', $headerRow);
        $columnIndex = array_search($lowercaseFieldName, $lowercaseHeaderRow);
        if ($columnIndex !== false) {
            return $this->getColumnLetter($columnIndex + 1);
        }
        return null;
    }
}
