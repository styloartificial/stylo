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
        $apiKey = config('services.openai.key');

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

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
            'ark-beta-mcp' => 'true',
        ])
            ->withOptions([
                'stream' => true,
            ])
            ->post(
                'https://ark.ap-southeast.bytepluses.com/api/v3/responses',
                [
                    'model' => 'seed-2-0-lite-260228',
                    'stream' => true,
                    'tools' => [
                        [
                            'type' => 'mcp',
                            'server_label' => 'deepwiki',
                            'server_url' => 'https://mcp.deepwiki.com/mcp',
                            'require_approval' => 'never',
                        ]
                    ],
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
                'BytePlus API Error: ' . $response->body()
            );
        }

        $body = $response->toPsrResponse()->getBody();

        $buffer = '';
        $fullText = '';

        while (!$body->eof()) {

            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n")) !== false) {

                $line = substr($buffer, 0, $pos);

                $buffer = substr($buffer, $pos + 1);

                $line = trim($line);

                if (empty($line)) {
                    continue;
                }

                if (!str_starts_with($line, 'data:')) {
                    continue;
                }

                $payload = trim(substr($line, 5));

                if ($payload === '[DONE]') {
                    continue;
                }

                $json = json_decode($payload, true);

                if (!$json) {
                    continue;
                }

                if (($json['type'] ?? null) === 'response.output_text.delta') {

                    $delta = $json['delta'] ?? '';

                    $fullText .= $delta;
                }
            }
        }

        if (empty($fullText)) {
            throw new \Exception('Empty response from BytePlus');
        }

        $fullText = trim($fullText);

        $fullText = preg_replace('/^```json\s*/i', '', $fullText);
        $fullText = preg_replace('/^```\s*/i', '', $fullText);
        $fullText = preg_replace('/\s*```$/i', '', $fullText);

        $fullText = trim($fullText);

        preg_match('/\{(?:[^{}]|(?R))*\}/s', $fullText, $matches);

        $jsonString = $matches[0] ?? null;

        if (!$jsonString) {
            throw new \Exception(
                "No JSON object found.\n\nRaw:\n" . $fullText
            );
        }

        $jsonString = preg_replace('/[\x00-\x1F\x7F]/u', '', $jsonString);

        $decoded = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {

            throw new \Exception(
                'Invalid JSON response: '
                    . json_last_error_msg()
                    . "\n\nRaw JSON:\n"
                    . $jsonString
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
