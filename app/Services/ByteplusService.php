<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ByteplusService
{
    public static function run(string $prompt, array $imagesUrl = [], int $generateImages = 1): array
    {

        if (!$prompt) {
            throw new \InvalidArgumentException('Prompt is required');
        }

        $analysis = self::analyze($prompt, $imagesUrl);

        $promptForImageGen = "Edit foto orang ini berdasarkan summary berikut dan hasilkan 3 foto dengan pose gerakan yang berbeda. " . ($analysis['summary'] ?? '');
        $images = self::generateImages($promptForImageGen, $imagesUrl[0], $generateImages);

        return [
            'analysis' => $analysis,
            'images' => $images,
        ];
    }

    protected static function analyze(string $prompt, array $imagesUrl): array
    {
        $apiKey = config('services.openai.key');

        $content = [
            [
                'type' => 'input_text',
                'text' => $prompt,
            ],
        ];

        foreach ($imagesUrl as $url) {
            $content[] = [
                'type' => 'input_image',
                'image_url' => $url,
            ];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
            'ark-beta-mcp' => 'true',
        ])->post('https://ark.ap-southeast.bytepluses.com/api/v3/responses', [
            'model' => 'seed-2-0-lite-250328',
            'stream' => false,
            'input' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception(
                'BytePlus API Error: ' . $response->body()
            );
        }

        $data = $response->json();

        $text = '';

        foreach (($data['output'] ?? []) as $output) {

            if (($output['type'] ?? null) !== 'message') {
                continue;
            }

            foreach (($output['content'] ?? []) as $item) {

                if (($item['type'] ?? null) === 'output_text') {
                    $text .= $item['text'] ?? '';
                }
            }
        }

        if (empty($text)) {
            throw new \Exception(
                'Empty response from BytePlus: ' . json_encode($data)
            );
        }

        $text = trim($text);

        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/^```\s*/i', '', $text);
        $text = preg_replace('/\s*```$/i', '', $text);

        $text = trim($text);

        preg_match('/\{.*\}/s', $text, $matches);

        $jsonString = $matches[0] ?? $text;

        $decoded = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(
                'Invalid JSON response: ' .
                    json_last_error_msg() .
                    "\n\nRaw response:\n" .
                    $text
            );
        }

        return $decoded;
    }

    protected static function generateImages(string $prompt, string $imageUrl, int $count): array
    {
        $apiKey = config('services.openai.key');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $apiKey",
        ])->post('https://ark.ap-southeast.bytepluses.com/api/v3/images/generations', [
            'model' => 'seedream-4-0-250828',
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

        $data = $response->json('data');
        return collect($data)->pluck('url')->values()->toArray();
    }
}
