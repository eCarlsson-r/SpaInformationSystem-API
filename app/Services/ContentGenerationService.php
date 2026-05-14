<?php

namespace App\Services;

use App\Ai\Agents\ContentDescriptionAgent;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Image;

/**
 * ContentGenerationService
 *
 * Generates AI-powered descriptions and images for spa content using the
 * Laravel AI SDK. Text generation uses the configured default provider
 * (OpenAI by default, with Ollama as an alternative via config/ai.php).
 * Image generation uses OpenAI DALL-E 3.
 */
class ContentGenerationService
{
    /**
     * Generate a compelling description for a spa content type.
     *
     * @param  string $type   e.g. "treatment", "room", "branch"
     * @param  array  $fields Key-value pairs describing the content
     * @return string The generated description (under 100 words)
     *
     * @throws \Throwable on generation failure
     */
    public function generateDescription(string $type, array $fields): string
    {
        $fieldsJson = json_encode($fields);
        $prompt     = "Generate a description for this {$type} with the following details: {$fieldsJson}";

        try {
            $description = trim((string) (new ContentDescriptionAgent)->prompt($prompt));

            if (empty($description)) {
                throw new \RuntimeException('AI agent returned an empty description.');
            }

            return $description;
        } catch (\Throwable $e) {
            Log::error('ContentGenerationService: Description generation failed', [
                'type'  => $type,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate an image for a spa content type using DALL-E 3.
     *
     * The image is stored on the default filesystem disk and the public URL
     * is returned. The caller is responsible for making the disk publicly
     * accessible (e.g. running `php artisan storage:link`).
     *
     * @param  string $type   e.g. "treatment", "room"
     * @param  string $prompt Descriptive context for the image
     * @return string The stored image path (relative to the disk root)
     *
     * @throws \Throwable on generation failure
     */
    public function generateImage(string $type, string $prompt): string
    {
        $fullPrompt = "A high-quality, professional photograph for a premium spa website. "
            . "Subject: {$type}. Context: {$prompt}. Elegant, serene, high-end aesthetics.";

        try {
            $image = Image::of($fullPrompt)
                ->landscape()
                ->quality('hd')
                ->generate(provider: Lab::OpenAI, model: 'dall-e-3');

            // Store the image on the default disk and return the path
            $path = $image->storePublicly('ai-images');

            if (empty($path)) {
                throw new \RuntimeException('AI image generation returned an empty path after storage.');
            }

            return $path;
        } catch (\Throwable $e) {
            Log::error('ContentGenerationService: Image generation failed', [
                'type'  => $type,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
