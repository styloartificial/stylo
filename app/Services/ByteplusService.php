<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ByteplusService
{
    public static function run(string $prompt, array $imagesUrl = [], int $generateImages = 1, bool $isHijab = false): array
    {
        \Illuminate\Support\Facades\Log::info("[BYTEPLUSSERVICE] Payload to BytePlusService", [
            // 'prompt' => $prompt,
            'images_url' => $imagesUrl,
            'generate_images' => $generateImages,
            'is_hijab' => $isHijab,
        ]);

        if (!$prompt) {
            throw new \InvalidArgumentException('Prompt is required');
        }

        $analysis = self::analyze($prompt, $imagesUrl);

        $modestRule = $isHijab
            ? "REQUIRED: syar'i hijab outfit — all hair covered by hijab, neck covered, long sleeves, ankle-length pants/skirt, loose clothing not hugging the body. PROHIBITED: hair/hairline visible, tight clothing, transparent fabric, skin visible except face and hands."
            : "REQUIRED: modest and neat clothing — not too tight, not too revealing. PROHIBITED: clothing that overly emphasizes body curves, too much exposed skin.";


        $promptForImageGen = "Edit this person's photo according to the outfit description below and generate 3 photos.
            REQUIRED: natural standing pose like a fashion catalog shoot (Uniqlo/Zara/H&M style).
            PROHIBITED: sitting, lying down, or emphasizing body curves.
            PROHIBITED: thighs, stomach, or back visible.
            {$modestRule}
            " . ($analysis['visual_prompt'] ?? $analysis['summary'] ?? '');

        \Illuminate\Support\Facades\Log::info("Prompt for image generation: " . $promptForImageGen);
        $images = self::generateImages($promptForImageGen, $imagesUrl[0], $generateImages);

        \Illuminate\Support\Facades\Log::info("BytePlusService result: " . json_encode([
            'analysis' => $analysis,
            'images' => $images,
        ]));

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
        ])
            ->timeout(120)
            ->connectTimeout(30)
            ->retry(3, 5000, throw: false)
            ->post(
                'https://ark.ap-southeast.bytepluses.com/api/v3/responses',
                [
                    'model' => 'seed-2-0-mini-260215',
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
                'BytePlus API Error: ' . $response->body()
            );
        }

        // Extract text from output[*].content[*].text (type: output_text)
        $fullText = '';
        $outputs = $response->json('output') ?? [];

        foreach ($outputs as $output) {
            foreach ($output['content'] ?? [] as $part) {
                if (($part['type'] ?? '') === 'output_text') {
                    $fullText .= ($part['text'] ?? '');
                }
            }
        }

        if (empty($fullText)) {
            throw new \Exception('Empty response from BytePlus. Body: ' . $response->body());
        }

        $fullText = trim($fullText);

        // Strip markdown code fences
        $fullText = preg_replace('/^```(?:json)?\s*/i', '', $fullText);
        $fullText = preg_replace('/\s*```$/i', '', $fullText);
        $fullText = trim($fullText);

        // Extract outermost JSON object
        $start = strpos($fullText, '{');
        $end = strrpos($fullText, '}');

        if ($start === false || $end === false || $end <= $start) {
            throw new \Exception(
                "No JSON object found.\n\nRaw:\n" . $fullText
            );
        }

        $jsonString = substr($fullText, $start, $end - $start + 1);

        // Remove non-printable control characters (preserve \t \n \r)
        $jsonString = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $jsonString);

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
                'model' => 'seedream-4-5-251128',
                'prompt' => $prompt,
                // 'negative_prompt' => 'sitting, lying down, bending over, tight clothes, form-fitting outfit, body-hugging, revealing clothes, exposed skin, bare legs, bare arms, bare stomach, bare chest, bare back, visible thighs, cleavage, short skirt, short dress, crop top, transparent fabric, sexy pose, provocative pose, nsfw',
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

        $log = $response->json();
        \Illuminate\Support\Facades\Log::info("BytePlus image generation response", [
            'status' => $response->status(),
            'body' => $log,
        ]);

        $data = $response->json('data');
        return collect($data)->pluck('url')->values()->toArray();
    }
}
