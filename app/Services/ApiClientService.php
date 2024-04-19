<?php

namespace App\Services;

use GuzzleHttp;

class ApiClientService
{
    public function __construct(
        protected XMLService $xmlService,
        protected ExcelService $excelService
    ) {}

    public function getAccessToken(): string
    {
        $client = new GuzzleHttp\Client([
            'verify' => false,
        ]);

        $response = $client->post('https://api.adviz.pro/api/auth/login', [
            'json' => [
                'email' => config('services.adviz.email'),
                'password' => config('services.adviz.password'),
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true)['access_token'];
    }

    public function getDataServiceAccount(): string
    {
        $client = new GuzzleHttp\Client([
            'verify' => false,
        ]);

        $response = $client->get('https://api.adviz.pro/api/google/account', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Accept' => 'application/json',
            ]
        ]);

        return $response->getBody()->getContents();
    }

    public function getDataAboutList(string $source): ?array
    {
        $categories = $this->xmlService->getCategories($source);
        return $categories;



        return $treePath;

        $treePath = ["Услуги", "Предложения услуг", "Другое"];
        $hash = md5(implode('', $treePath));

        $treePath2 = ["Услуги", "Предложения услуг", "Мастер на час"];
        $hash2 = md5(implode('', $treePath2));

        $client = new GuzzleHttp\Client([
            'verify' => false,
        ]);

        $response = $client->get('https://api.adviz.pro/api/avito/category', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Accept' => 'application/json',
            ],
            'json' => [$hash, $hash2],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);

        return $data;
    }
}
