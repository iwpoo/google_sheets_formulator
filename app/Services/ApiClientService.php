<?php

namespace App\Services;

use GuzzleHttp;

class ApiClientService
{
    public function getAccessToken(): string
    {
        $client = new GuzzleHttp\Client([
            'verify' => false,
        ]);

        $response = $client->post('https://api.adviz.pro/api/auth/login', [
            'json' => [
                'email' => 'support@web-kiborg.ru',
                'password' => 'dMu6cQuGnu8qB3R',
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
        //$categories = $this->getCategories($source);

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
