<?php

namespace App\Services;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp;

class XMLService
{
    public function __construct(
        protected ApiClientService $apiClientService
    ) {}

    public function getData(string $source): mixed
    {
        try {
            $response = Http::withOptions([
                'verify' => false,
            ])->get($source);

            if ($response->successful()) {
                $xmlContent = $response->body();
                $xmlObject = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
                $jsonString = json_encode($xmlObject);
                return json_decode($jsonString, true)['Ad'];
            } else {
                Log::error('Failed to fetch XML from ' . $source . ' with status code: ' . $response->status());
                return NULL;
            }
        } catch (Exception $e) {
            Log::error('Error processing XML from ' . $source . ': ' . $e->getMessage());
            return NULL;
        }
    }

    public function getCategories(string $source): array
    {
        $categories = [];
        $data = $this->getData($source);

        foreach ($data as $item) {
            $categories[] = $item['Category'];
        }

        return array_unique($categories);
    }

    public function getDataOfCategory(string $source, string $category): array
    {
        $data = $this->getData($source);
        $dataOfCategory = [];

        foreach ($data as $item) {
            if ($item['Category'] === $category) {
                $dataOfCategory[] = $item;
            }
        }

        return $dataOfCategory;
    }

    public function fetchSheetLinkXML(string $source): ?array
    {
        $categories = $this->getCategories($source);

        $treePath = ["Услуги", "Предложения услуг", "Другое"];
        $hash = md5(implode('', $treePath));

        $treePath2 = ["Услуги", "Предложения услуг", "Мастер на час"];
        $hash2 = md5(implode('', $treePath2));

        $client = new GuzzleHttp\Client([
            'verify' => false,
        ]);

        $response = $client->get('https://api.adviz.pro/api/avito/category', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiClientService->getAccessToken(),
                'Accept' => 'application/json',
            ],
            'json' => [$hash, $hash2],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data;
    }

    public function checkForXmlFormat(string $source): bool
    {
        return filter_var($source, FILTER_VALIDATE_URL) && pathinfo($source, PATHINFO_EXTENSION) === 'xml';
    }
}
