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

    public static function analyze(string $prompt, array $imagesUrl): array
    {
        if (blank($prompt)) {
            throw new \InvalidArgumentException('Prompt is required');
        }

        if (empty($imagesUrl)) {
            throw new \InvalidArgumentException('Image is required');
        }

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

        $response = Http::withToken(config('services.openai.key'))
            ->acceptJson()
            ->contentType('application/json')
            ->timeout(180)
            ->connectTimeout(30)
            ->retry(3, 3000, throw: false)
            ->post(
                'https://ark.ap-southeast.bytepluses.com/api/v3/responses',
                [
                    'model' => 'seed-2-0-lite-260228',

                    // lebih stabil untuk queue/background job
                    'stream' => false,

                    'input' => [
                        [
                            'role' => 'user',
                            'content' => $content,
                        ]
                    ]
                ]
            );

        if (!$response->successful()) {

            throw new \Exception(
                'BytePlus API Error: ' .
                    $response->status() .
                    ' - ' .
                    $response->body()
            );
        }

        $text =
            data_get($response->json(), 'output.0.content.0.text')
            ?? data_get($response->json(), 'output_text')
            ?? null;

        if (!$text) {

            throw new \Exception(
                'Empty response from BytePlus'
            );
        }

        $text = trim($text);

        // hapus markdown block
        $text = preg_replace('/^```json\s*/i', '', $text);
        $text = preg_replace('/^```\s*/i', '', $text);
        $text = preg_replace('/\s*```$/i', '', $text);

        $text = trim($text);

        // ambil object json pertama
        preg_match('/\{(?:[^{}]|(?R))*\}/s', $text, $matches);

        $jsonString = $matches[0] ?? null;

        if (!$jsonString) {

            throw new \Exception(
                "No JSON object found.\n\nRaw:\n" . $text
            );
        }

        // bersihkan invisible chars
        $jsonString = preg_replace(
            '/[\x00-\x1F\x7F]/u',
            '',
            $jsonString
        );

        $decoded = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {

            throw new \Exception(
                'Invalid JSON response: ' .
                    json_last_error_msg() .
                    "\n\nRaw JSON:\n" .
                    $jsonString
            );
        }

        return $decoded;
    }

    public static function generateImages(string $prompt, string $imageUrl, int $count): array
    {
        $apiKey = config('services.openai.key');

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $apiKey",
        ])->timeout(180)->connectTimeout(30)->retry(3, 3000, throw: false)
            ->post('https://ark.ap-southeast.bytepluses.com/api/v3/images/generations', [
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
