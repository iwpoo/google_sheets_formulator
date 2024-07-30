<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiClientService
{
    private string|null $accessToken = NULL;

    public function __construct(public int $timeout = 20)
    {
    }

    public function getHttpClient(): PendingRequest
    {
        return Http::timeout($this->timeout)->throw();
    }

    /**
     * @return string
     * @throws \Throwable
     */
    public function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            $data = $this->getHttpClient()->acceptJson()->post(config('services.adviz.url') . '/auth/login', [
                'email' => config('services.adviz.email'),
                'password' => config('services.adviz.password'),
            ])->json();

            $this->accessToken = $data['access_token'];
        } catch (\Throwable $e) {
            Log::error('Error getting access token: ' . $e->getMessage());
            throw $e;
        }

        return $this->accessToken;
    }

    /**
     * @return string
     * @throws \Throwable
     */
    public function getDataServiceAccount(): string
    {
        try {
            $data = $this->getHttpClient()->withToken($this->getAccessToken())->acceptJson()
                ->get(config('services.adviz.url') . '/google/account')->json();

            return json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            Log::error('Error getting data service account: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @throws \Throwable
     */
    public function getAvitoCategories(array $hashes): array
    {
        try {
            $response = $this->getHttpClient()->withToken($this->getAccessToken())->acceptJson()
                ->get(config('services.adviz.url') . '/avito/category', $hashes);

            return (array)$response->json();
        } catch (\Throwable $e) {
            Log::error('[ApiClientService -> getAvitoCategories] Error fetching sheet links: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @return array
     * @throws \Throwable
     */
    public function getParserAvitoCategories(): array
    {
        try {
            $response = $this->getHttpClient()->acceptJson()->withToken(config('services.parser_adviz.token'))
                ->get(config('services.parser_adviz.url') . '/autoload/categories');

            return (array)$response->json();
        } catch (\Throwable $e) {
            Log::error('Error getting data avito categories: ' . $e->getMessage());
            throw $e;
        }
    }
}
