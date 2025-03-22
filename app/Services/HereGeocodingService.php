<?php

namespace App\Services;

use GuzzleHttp\Client;

class HereGeocodingService
{
    protected $client;
    protected $apiKey;

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = env('HERE_API_KEY');
    }

    public function getCoordinates($address)
    {
        $url = "https://geocode.search.hereapi.com/v1/geocode?q=" . urlencode($address) . "&apiKey=" . $this->apiKey;

        $response = $this->client->get($url);
        $data = json_decode($response->getBody(), true);

        if (!empty($data['items'])) {
            $location = $data['items'][0]['position'];
            return [
                'latitude' => $location['lat'],
                'longitude' => $location['lng']
            ];
        }

        return null;
    }
}
