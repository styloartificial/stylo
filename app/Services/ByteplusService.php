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
                'text' => $prompt
            ]
        ];
        foreach ($imagesUrl as $url) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $url,
                ],
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
            ->post('https://ark.ap-southeast.bytepluses.com/api/v3/responses', [
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
            ]);

        $body = $response->toPsrResponse()->getBody();

        $buffer = '';
        $structured = null;

        while (!$body->eof()) {

            $chunk = $body->read(1024);

            $lines = explode("\n", $chunk);

            foreach ($lines as $line) {

                $line = trim($line);

                if (!$line) continue;

                if (str_starts_with($line, 'data:')) {
                    $line = trim(substr($line, 5));
                }

                if ($line === '[DONE]') continue;

                $json = json_decode($line, true);

                if (!$json) continue;

                if (($json['type'] ?? null) === 'response.completed') {

                    $output = $json['response']['output'] ?? [];

                    foreach ($output as $item) {

                        if (($item['type'] ?? null) === 'message') {

                            foreach ($item['content'] ?? [] as $content) {

                                if (($content['type'] ?? null) === 'output_text') {

                                    $text = $content['text'] ?? '';

                                    $decoded = json_decode($text, true);

                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        $structured = $decoded;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $structured;
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
