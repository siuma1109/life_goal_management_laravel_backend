<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiService
{
    public function fetchAIDetails(string $prompt)
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . env('DIFY_API_KEY'),
        ])
            ->withBody(json_encode([
                'query' => $prompt,
                'user' => 1,
                'inputs' => [
                    'role' => 'user',
                    'goal' => $prompt,
                ],
            ]), 'application/json')
            ->post('https://api.dify.ai/v1/chat-messages');

        if ($response->successful()) {
            $json = $response->json();
            if (isset($json['answer'])) {
                $answerString = $json['answer'];

                // Clean the answer string - extract content from ```json blocks
                $cleanedAnswerString = preg_replace('/^```json\s*|\s*```$/s', '', $answerString);

                // Decode the JSON string into an array
                $decodedAnswer = json_decode($cleanedAnswerString, true);
                return $decodedAnswer;
            }
        }

        return null;
    }
}
