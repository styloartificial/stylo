<?php

namespace App\Services;

use OpenAI;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OpenAIService
{
    protected static function client()
    {
        return OpenAI::client(config('services.openai.key'));
    }

    public static function run(array $payload): array
    {
        $prompt = $payload['prompt'] ?? '';
        $tempImages = $payload['temp_images'] ?? [];
        $generateImages = (int) ($payload['generate_images'] ?? 0);

        if (!$prompt) {
            throw new \InvalidArgumentException('Prompt is required');
        }

        return [
            'analysis' => self::analyze($prompt, $tempImages),
            'images' => $generateImages > 0
                ? self::generateImages($prompt, $generateImages)
                : [],
        ];
    }

    protected static function analyze(string $prompt, array $tempImages): array
    {
        $client = self::client();

        $content = [
            [
                'type' => 'text',
                'text' => $prompt . "\n\nReturn JSON only.",
            ]
        ];

        foreach ($tempImages as $fileName) {
            $path = "temp/{$fileName}";

            if (!Storage::disk('local')->exists($path)) {
                continue;
            }

            $content[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => self::tempFileToBase64($path),
                ],
            ];
        }

        $response = $client->chat()->create([
            'model' => 'gpt-4.1',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
        ]);

        return self::safeJsonDecode(
            $response->choices[0]->message->content ?? '{}'
        );
    }

    protected static function generateImages(string $prompt, int $count): array
    {
        $client = self::client();

        $response = $client->images()->create([
            'model' => 'gpt-image-1',
            'prompt' => $prompt,
            'size' => '1024x1024',
            'n' => $count,
        ]);

        $files = [];

        foreach ($response->data as $img) {
            $uuid = (string) Str::uuid() . '.png';

            Storage::disk('local')->put(
                "temp/{$uuid}",
                base64_decode($img->b64_json)
            );

            $files[] = $uuid;
        }

        return $files;
    }

    protected static function tempFileToBase64(string $path): string
    {
        $fullPath = storage_path("app/{$path}");
        $mime = mime_content_type($fullPath);
        $data = base64_encode(file_get_contents($fullPath));

        return "data:{$mime};base64,{$data}";
    }

    protected static function safeJsonDecode(string $text): array
    {
        $json = trim($text);

        // remove ```json ``` if exists
        $json = preg_replace('/^```json|```$/m', '', $json);

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [
            '_raw' => $text,
            '_error' => 'Invalid JSON from OpenAI'
        ];
    }
}
