<?php

namespace App\Services;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ClearValuesRequest;
use Google\Service\Sheets\CopySheetToAnotherSpreadsheetRequest;
use Google\Service\Sheets\ValueRange;
use Google_Service_Sheets_BatchUpdateSpreadsheetRequest;
use Google_Service_Sheets_DeleteSheetRequest;
use Google_Service_Sheets_Request;
use Google_Service_Sheets_UpdateSheetPropertiesRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GoogleSheetService
{
    public function __construct(protected ApiClientService $apiClientService)
    {
    }

    /**
     * @param string $spreadsheetId
     * @param string $sheetId
     * @param string $sheetName
     * @param array $data
     * @return void
     * @throws RequestException
     * @throws \Google\Exception
     * @throws \Throwable
     */
    public function insertData(string $spreadsheetId, string $sheetId, string $sheetName, array $data = []): void
    {
        try {
            $client = new Client();
            $dataAccountJson = $this->apiClientService->getDataServiceAccount();
            $uniqueId = Str::uuid();
            Storage::put("credentials/google_credentials_$uniqueId.json", $dataAccountJson);
            $dataJson = storage_path("app/credentials/google_credentials_$uniqueId.json");
            $client->setAuthConfig($dataJson);
            $client->addScope(Sheets::SPREADSHEETS);

            $service = new Sheets($client);

            Log::debug('[GoogleSheetService -> insertData] Начала копирования шаблона');
            $this->copySheetToAnotherSpreadsheet($service, $spreadsheetId, $sheetId);

            $highestNumberSheet = NULL;
            $highestNumber = -1;

            Log::debug('[GoogleSheetService -> insertData] Начала получения таблицы');
            $spreadsheet = $service->spreadsheets->get($spreadsheetId);

            foreach ($spreadsheet->getSheets() as $sheet) {
                $title = $sheet->getProperties()->getTitle();
                if (preg_match('/#(\d+)$/', $title, $matches)) {
                    $number = (int)$matches[1];
                    if ($number > $highestNumber) {
                        $highestNumber = $number;
                        $highestNumberSheet = $title;
                    }
                }
            }
            unset($spreadsheet);

            if ($highestNumberSheet === NULL) {
                $highestNumberSheet = $sheetName;
            }

            Log::debug('[GoogleSheetService -> insertData] Начало очистки данных');
            $clearRange = "$highestNumberSheet!3:1000";
            $clearRequest = new ClearValuesRequest();
            $service->spreadsheets_values->clear($spreadsheetId, $clearRange, $clearRequest);

            $data = array_map('array_change_key_case', $data);

            $response = $service->spreadsheets_values->get($spreadsheetId, "$highestNumberSheet!2:2");
            $headerRow = $response->getValues()[0];

            $columnValues = [];
            $idColumnIndex = array_search('id', array_map('strtolower', $headerRow));

            $currentData = $service->spreadsheets_values->get($spreadsheetId, "$highestNumberSheet!" . $this->getColumnLetter($idColumnIndex + 1) . '3:' . $this->getColumnLetter($idColumnIndex + 1) . (count($data) + 2))->getValues();
            $existingIds = !empty($currentData) ? array_column($currentData, 0) : [];

            usort($data, static function (array $a, array $b): int {
                $dateA = $a['datebegin'] ?? 0;
                $dateB = $b['datebegin'] ?? 0;
                return strtotime($dateA) <=> strtotime($dateB);
            });

            foreach ($data as $item) {
                if (isset($item['id']) && in_array($item['id'], $existingIds)) {
                    continue;
                }

                $item['datebegin'] = 'сразу';

                if (isset($item['images'])) {
                    $imageUrls = [];

                    if (is_array($item['images']) && isset($item['images']['Image'])) {
                        foreach ($item['images']['Image'] as $image) {
                            if (isset($image['@attributes']['url'])) {
                                $imageUrls[] = $image['@attributes']['url'];
                            }
                        }
                    } else {
                        foreach ($item['images'] as $image) {
                            if (isset($image['@attributes']['url'])) {
                                $imageUrls[] = $image['@attributes']['url'];
                            }
                        }
                    }

                    $item['images'] = implode("\n", $imageUrls);
                } elseif (isset($item['imageurls'])) {
                    $imageUrls = explode("|", $item['imageurls']);
                    $item['images'] = implode("\n", $imageUrls);
                }

                foreach ($item as $key => $value) {
                    if (is_array($value)) {
                        continue;
                    }
                    $columnLetter = $this->searchColumnLetter($key, $headerRow);
                    if ($columnLetter !== NULL) {
                        $rowIndex = count($existingIds) + 3;
                        $range = "$columnLetter$rowIndex";
                        $columnValues[$range][] = [$value ?? NULL];
                    }
                }
            }

            Log::debug('[GoogleSheetService -> insertData] Начало обновления данных');
            foreach ($columnValues as $range => $values) {
                $service->spreadsheets_values->update($spreadsheetId, "$highestNumberSheet!$range", new ValueRange(['values' => $values]), ['valueInputOption' => 'RAW']);
            }

            Storage::delete("credentials/google_credentials_$uniqueId.json");
        } catch (\Throwable $e) {
            Log::error('Error inserting data into Google Sheets: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param Sheets $service
     * @param string $spreadsheetId
     * @param string $sheetId
     * @return void
     * @throws \Google\Service\Exception
     */
    private function copySheetToAnotherSpreadsheet(Sheets $service, string $spreadsheetId, string $sheetId): void
    {
        $templateSpreadsheet = $service->spreadsheets->get($sheetId);

        foreach ($templateSpreadsheet['sheets'] as $templateSheet) {
            $templateSheetId = $templateSheet['properties']['sheetId'];

            $copySheetToAnotherSpreadsheetRequest = new CopySheetToAnotherSpreadsheetRequest([
                'destinationSpreadsheetId' => $spreadsheetId,
            ]);
            $service->spreadsheets_sheets->copyTo($sheetId, $templateSheetId, $copySheetToAnotherSpreadsheetRequest);
        }

        $targetSpreadsheet = $service->spreadsheets->get($spreadsheetId);
        $existingTitles = array_map(function ($sheet) {
            return $sheet['properties']['title'];
        }, $targetSpreadsheet->getSheets());

        foreach ($targetSpreadsheet->getSheets() as $sheet) {
            $_sheetId = $sheet->getProperties()->getSheetId();
            $sheetTitle = $sheet->getProperties()->getTitle();
            $newSheetTitle = str_replace(' (копия)', '', $sheetTitle);

            if ($sheetTitle !== $newSheetTitle) {
                $uniqueTitle = $newSheetTitle;
                $suffix = 1;

                while (in_array($uniqueTitle, $existingTitles)) {
                    $uniqueTitle = "$newSheetTitle #$suffix";
                    $suffix++;
                }

                $existingTitles[] = $uniqueTitle;

                $updateSheetPropertiesRequest = new Google_Service_Sheets_UpdateSheetPropertiesRequest([
                    'properties' => [
                        'sheetId' => $_sheetId,
                        'title' => $uniqueTitle,
                    ],
                    'fields' => 'title',
                ]);

                $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                    'requests' => [
                        ['updateSheetProperties' => $updateSheetPropertiesRequest]
                    ]
                ]);

                $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
            }
        }

        foreach ($targetSpreadsheet['sheets'] as $sheet) {
            if ($sheet['properties']['title'] === 'Лист1') {
                $response = $service->spreadsheets_values->get($spreadsheetId, 'Лист1!A1');
                $cellValue = $response->getValues();

                if (empty($cellValue)) {
                    $deleteRequest = new Google_Service_Sheets_Request([
                        'deleteSheet' => new Google_Service_Sheets_DeleteSheetRequest([
                            'sheetId' => $sheet['properties']['sheetId'],
                        ]),
                    ]);
                    $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                        'requests' => [$deleteRequest],
                    ]);
                    $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
                }
                break;
            }
        }
    }

    /**
     * @param int|string $columnNumber
     * @return string
     */
    private function getColumnLetter(int|string $columnNumber): string
    {
        $temp = '';
        while ($columnNumber > 0) {
            $modulo = ($columnNumber - 1) % 26;
            $temp = chr(65 + $modulo) . $temp;
            $columnNumber = (int)(($columnNumber - $modulo) / 26);
        }
        return $temp;
    }

    /**
     * @param string $fieldName
     * @param array $headerRow
     * @return string|null
     */
    private function searchColumnLetter(string $fieldName, array $headerRow): ?string
    {
        $lowercaseFieldName = strtolower($fieldName);
        $lowercaseHeaderRow = array_map('strtolower', $headerRow);
        $columnIndex = array_search($lowercaseFieldName, $lowercaseHeaderRow);
        if ($columnIndex !== FALSE) {
            return $this->getColumnLetter($columnIndex + 1);
        }
        return NULL;
    }
}
