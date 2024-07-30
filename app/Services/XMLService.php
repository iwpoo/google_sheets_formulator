<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sabre\Xml\Service as Xml;

class XMLService extends BaseWorkService
{
    public function __construct(protected ApiClientService $apiClientService, private string|null $xmlData = NULL // сделано для оптимизации чтобы дважды не получать тело файла
    )
    {
    }

    /**
     * @param string $source
     * @return array|null
     */
    public function getData(string $source): array|null
    {
        try {
            if (is_null($this->xmlData)) {
                // По идее, xmlData никогда не будет пустым, так как мы проверяем checkForXmlFormat перед запуском данного метода
                $response = Http::withoutVerifying()->timeout(60)->accept('application/xml')->get($source);
                if (!$response->successful()) {
                    Log::error('Failed to fetch XML from ' . $source . ' with status code: ' . $response->status());
                    return NULL;
                }

                $this->xmlData = $response->body();
            }

            $xmlObject = simplexml_load_string($this->xmlData, 'SimpleXMLElement', LIBXML_NOCDATA);
            libxml_clear_errors();
            if ($xmlObject === FALSE) {
                throw new \RuntimeException('Failed to parse XML');
            }

            $jsonString = json_encode($xmlObject, JSON_THROW_ON_ERROR);
            return (array)json_decode($jsonString, TRUE, 512, JSON_THROW_ON_ERROR)['Ad'];
        } catch (\Exception $e) {
            Log::error('Error processing XML from ' . $source . ': ' . $e->getMessage());
            return NULL;
        }
    }

    /**
     * @param array $data
     * @return array|null
     */
    public function fetchSheetLinkXML(array $data = []): ?array
    {
        try {
            $hashes = $this->generateHashes($data);

            return $this->apiClientService->getAvitoCategories($hashes);
        } catch (\Throwable $e) {
            Log::error('[XMLService -> fetchSheetLinkXML] Error fetching sheet links: ' . $e->getMessage(), ['exception' => $e]);
            return NULL;
        }
    }

    /**
     * @param string $source
     * @return bool
     */
    public function checkForXmlFormat(string $source): bool
    {
        $response = Http::withoutVerifying()->timeout(60)->accept('application/xml')->get($source);
        if ($response->successful() && Str::contains($response->header('Content-Type'), [
                'text/xml',
                'application/xml',
            ])) {
            $this->xmlData = $response->body();

            libxml_use_internal_errors(TRUE);
            $xml = simplexml_load_string($this->xmlData);

            if ($xml === FALSE) {
                Log::debug('[XMLService -> checkForXmlFormat] Файл XML не валидный', [
                    'xml_errors' => libxml_get_errors(),
                    'body' => $this->xmlData,
                ]);
            }

            libxml_clear_errors();

            return $xml !== FALSE;
        }

        Log::debug('[XMLService -> checkForXmlFormat] Ссылка не прошла проверку', [
            'response_status_code' => $response->status(),
            'response_content_type' => $response->header('Content-Type'),
        ]);

        return FALSE;
    }
}
