<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PusherApiService
{
    private string $appId;
    private string $key;
    private string $secret;
    private string $cluster;

    public function __construct()
    {
        $this->appId = '1553073';
        $this->key = 'b395ac035994ca7af583';
        $this->secret = '8a20e39fc3f1ab6111af';
        $this->cluster = 'eu';

        Log::info("Pusher API: App ID: {$this->appId}");
        Log::info("Pusher API: Key: {$this->key}");
        Log::info("Pusher API: Secret: {$this->secret}");
        Log::info("Pusher API: Cluster: {$this->cluster}");
    }

    public function trigger(string $channel, string $event, array $data): bool
    {
        try {
            $url = "http://api-eu.pusher.com/apps/{$this->appId}/events";
            
            $timestamp = time();
            $body = json_encode([
                'name' => $event,
                'channel' => $channel,
                'data' => json_encode($data),
            ]);
            $bodyMd5 = md5($body);
            $authSignature = $this->generateSignature($timestamp, $bodyMd5);
            
            $urlWithAuth = $url . "?auth_key={$this->key}&auth_timestamp={$timestamp}&auth_version=1.0&body_md5={$bodyMd5}&auth_signature={$authSignature}";
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Host' => 'api-eu.pusher.com',
                'User-Agent' => 'Laravel-Pusher-API/1.0',
            ])->post($urlWithAuth, [
                'name' => $event,
                'channel' => $channel,
                'data' => json_encode($data),
            ]);

            if ($response->successful()) {
                Log::info("Pusher API: Event sent successfully to channel {$channel}");
                return true;
            } else {
                Log::error("Pusher API: Failed to send event", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'channel' => $channel,
                    'event' => $event
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Pusher API: Exception occurred", [
                'message' => $e->getMessage(),
                'channel' => $channel,
                'event' => $event
            ]);
            return false;
        }
    }

    private function generateSignature(int $timestamp, string $bodyMd5): string
    {
        $stringToSign = "POST\n/apps/{$this->appId}/events\nauth_key={$this->key}&auth_timestamp={$timestamp}&auth_version=1.0&body_md5={$bodyMd5}";
        
        return hash_hmac('sha256', $stringToSign, $this->secret);
    }
}
