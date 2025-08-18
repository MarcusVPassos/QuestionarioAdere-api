<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlueFleetService
{
    public function getAccessToken(): ?string
    {
        return Cache::remember('bluefleet_access_token', now()->addMinutes(55), function () {
            try {
                $clientId     = config('bluefleet.client_id');
                $clientSecret = config('bluefleet.client_secret');
                $authHeader   = base64_encode("{$clientId}:{$clientSecret}");

                $response = Http::asForm()
                    ->withHeaders([
                        'Authorization' => 'Basic ' . $authHeader,
                        'Accept'        => 'application/json',
                    ])
                    ->post(config('bluefleet.auth_url'), [
                        'grant_type' => 'client_credentials',
                    ]);

                if ($response->successful()) {
                    return $response->json('access_token');
                }

                Log::error('Erro ao obter token BlueFleet', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            } catch (\Throwable $e) {
                Log::error('Exceção ao obter token BlueFleet', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            Log::debug('[BlueFleet] Auth URL: ' . config('bluefleet.auth_url'));
            Log::debug('[BlueFleet] Client ID: ' . config('bluefleet.client_id'));
            Log::debug('[BlueFleet] Client Secret: ' . substr(config('bluefleet.client_secret'), 0, 5) . '...');


            return null;
        });
    }
}
