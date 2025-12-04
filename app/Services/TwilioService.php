<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class TwilioService
{
    /**
     * Fetch Twilio credentials from tblapis
     */
    protected function fetchTwilioCredentials(): array
    {
        $twilio = DB::table('tblapis')
            ->where('api_name', 'TWILIO')
            ->first();

        if (!$twilio) {
            throw new \RuntimeException('Twilio credentials not configured in tblapis.');
        }

        return [
            'account_sid' => $twilio->client_id,
            'auth_token' => $twilio->client_secret,
            'from_number' => $twilio->sys_phone_number,
        ];
    }

    /**
     * Core Twilio send function you can reuse
     */
    public function sendSystemSms(string $to, string $body): array
    {
        $creds = $this->fetchTwilioCredentials();
        $client = new Client($creds['account_sid'], $creds['auth_token']);

        try {
            $message = $client->messages->create($to, [
                'from' => $creds['from_number'],
                'body' => $body,
            ]);

            return [
                'success' => true,
                'sid' => $message->sid,
                'status' => $message->status,
                'error_code' => $message->errorCode,
                'error_message' => $message->errorMessage,
            ];
        } catch (\Twilio\Exceptions\RestException $e) {
            // Twilio-specific API error (403, 400, invalid number, etc.)
            \Log::error('Twilio API error: ' . $e->getMessage());

            return [
                'success' => false,
                'type' => 'TwilioRestException',
                'status_code' => $e->getStatusCode(),   // HTTP status
                'error_code' => $e->getCode(),         // Twilio API error code
                'error_message' => $e->getMessage(),
                'details' => $e->getMoreInfo(),     // Twilio help URL
            ];
        } catch (\Exception $e) {
            // General error
            \Log::error('Twilio general error: ' . $e->getMessage());

            return [
                'success' => false,
                'type' => 'Exception',
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
            ];
        }
    }

}
