<?php

namespace App\Http\Controllers;

use App\Services\TwilioService;
use Illuminate\Http\Request;

class TwilioController extends Controller
{
    protected TwilioService $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    /**
     * Optional: HTTP endpoint for testing from Postman
     */
    public function sendSmsEndpoint(Request $request)
    {
        $data = $request->validate([
            'to'   => 'required|string',
            'body' => 'required|string',
        ]);

        $result = $this->twilioService->sendSystemSms($data['to'], $data['body']);

        return response()->json($result);
    }
}
