<?php

namespace App\Helpers;

use OpenAI;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OpenAIHelper
{
    public static function prompt(string $promptText, array $imgs = []): array
    {
        $client = OpenAI::client(config('services.openai.key'));

        $content = [
            [
                'type' => 'text',
                'text' => $promptText,
            ]
        ];

        foreach ($imgs as $fileName) {
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

        $text = $response->choices[0]->message->content ?? '';

        $generatedImages = [];

        if (str_contains(strtolower($text), '[generate_image]')) {
            $imageResponse = $client->images()->generate([
                'model' => 'gpt-image-1',
                'prompt' => $promptText,
                'size' => '1024x1024',
            ]);

            foreach ($imageResponse->data as $img) {
                $uuid = Str::uuid() . '.png';
                Storage::disk('local')->put(
                    "temp/{$uuid}",
                    base64_decode($img->b64_json)
                );
                $generatedImages[] = $uuid;
            }
        }

        return [
            'text' => $text,
            'images' => $generatedImages,
        ];
    }
    
    protected static function tempFileToBase64(string $path): string
    {
        $mime = mime_content_type(storage_path("app/{$path}"));
        $data = base64_encode(Storage::disk('local')->get($path));

        return "data:{$mime};base64,{$data}";
    }
}
