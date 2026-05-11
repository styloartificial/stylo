<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ByteplusService
{
    private const BASE_URL = 'https://ark.ap-southeast.bytepluses.com/api/v3';

    private const ANALYZE_MODEL = 'seed-2-0-lite-260228';

    private const IMAGE_MODEL = 'seedream-4-0-250828';

    public static function run(
        string $prompt,
        array $imagesUrl = [],
        int $generateImages = 1
    ): array {

        if (blank($prompt)) {
            throw new \InvalidArgumentException('Prompt is required');
        }

        if (empty($imagesUrl)) {
            throw new \InvalidArgumentException('At least one image is required');
        }

        $analysis = self::analyze($prompt, $imagesUrl);

        $summary = data_get($analysis, 'summary', '');

        $imagePrompt = <<<PROMPT
Edit foto orang ini berdasarkan summary berikut.

{$summary}

Hasilkan {$generateImages} foto dengan pose berbeda,
tetap realistis, fashionable, dan konsisten dengan wajah asli.
PROMPT;

        $images = self::generateImages(
            prompt: $imagePrompt,
            imageUrl: $imagesUrl[0],
            count: $generateImages
        );

        return [
            'analysis' => $analysis,
            'images' => $images,
        ];
    }

    public static function analyze(
        string $prompt,
        array $imagesUrl
    ): array {

        $content = [
            [
                'type' => 'input_text',
                'text' => $prompt,
            ]
        ];

        foreach ($imagesUrl as $url) {
            $content[] = [
                'type' => 'input_image',
                'image_url' => $url,
            ];
        }

        $response = self::http()
            ->timeout(180)
            ->post('/responses', [
                'model' => self::ANALYZE_MODEL,

                // STREAM OFF = lebih stabil
                'stream' => false,

                'input' => [
                    [
                        'role' => 'user',
                        'content' => $content,
                    ]
                ]
            ]);

        if (!$response->successful()) {

            Log::error('BytePlus Analyze Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('BytePlus analyze failed');
        }

        $text = data_get($response->json(), 'output.0.content.0.text');

        if (!$text) {
            throw new \Exception('Empty analyze response');
        }

        return self::extractJson($text);
    }

    public static function generateImages(
        string $prompt,
        string $imageUrl,
        int $count = 1
    ): array {

        $response = self::http()
            ->timeout(300)
            ->post('/images/generations', [
                'model' => self::IMAGE_MODEL,
                'prompt' => $prompt,
                'image' => $imageUrl,

                'sequential_image_generation' => 'auto',

                'sequential_image_generation_options' => [
                    'max_images' => $count,
                ],

                'response_format' => 'url',

                'size' => '2K',

                'stream' => false,

                'watermark' => false,
            ]);

        if (!$response->successful()) {

            Log::error('BytePlus Image Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Generate image failed');
        }

        return collect($response->json('data'))
            ->pluck('url')
            ->filter()
            ->values()
            ->toArray();
    }

    private static function http()
    {
        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->contentType('application/json')
            ->withToken(config('services.openai.key'))
            ->connectTimeout(30)
            ->retry(
                3,
                3000,
                function ($exception) {
                    return true;
                },
                throw: false
            );
    }

    private static function extractJson(string $text): array
    {
        $text = trim($text);

        $text = preg_replace('/^```json/i', '', $text);
        $text = preg_replace('/^```/', '', $text);
        $text = preg_replace('/```$/', '', $text);

        $text = trim($text);

        preg_match('/\{(?:[^{}]|(?R))*\}/s', $text, $matches);

        $json = $matches[0] ?? null;

        if (!$json) {
            throw new \Exception('No JSON found');
        }

        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {

            throw new \Exception(
                json_last_error_msg()
            );
        }

        return $decoded;
    }
}