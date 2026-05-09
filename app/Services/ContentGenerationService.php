<?php

namespace App\Services;

use App\Ai\Agents\ContentDescriptionAgent;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Image;

/**
 * ContentGenerationService
 *
 * Generates descriptions and images for spa content using the Laravel AI SDK.
 *
 * Text generation supports OpenAI and Ollama (configured via config/ai.php).
 * Image generation supports OpenAI (DALL-E 3) only — Ollama does not support images.
 */
class ContentGenerationService
{
    private string $textDriver;
    private string $imageDriver;

    public function __construct()
    {
        $this->textDriver  = config('services.ai_text_driver', 'openai');
        $this->imageDriver = config('services.ai_image_driver', 'openai');
    }

    /**
     * Generate a description for the given content type and fields.
     *
     * @param  string $type   e.g. 'treatment', 'room', 'branch'
     * @param  array  $fields Key-value pairs describing the content.
     * @return string The generated description.
     *
     * @throws \Exception When the configured driver is unavailable.
     */
    public function generateDescription(string $type, array $fields): string
    {
        $fieldsJson = json_encode($fields, JSON_UNESCAPED_UNICODE);
        $prompt     = "Generate a description for this {$type} with the following details: {$fieldsJson}";

        // Resolve the provider for the Laravel AI SDK
        $provider = $this->resolveTextProvider();

        try {
            $response = (new ContentDescriptionAgent)->prompt(
                $prompt,
                provider: $provider,
            );

            $text = trim((string) $response);

            if (empty($text)) {
                throw new \RuntimeException('AI agent returned an empty description.');
            }

            return $text;
        } catch (\Throwable $e) {
            Log::error("ContentGenerationService ({$this->textDriver}): Description generation failed", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate an image URL for the given content type and prompt.
     *
     * @param  string $type   e.g. 'treatment', 'room'
     * @param  string $prompt Descriptive context for the image.
     * @return string The URL of the generated image.
     *
     * @throws \Exception When Ollama is selected (unsupported) or generation fails.
     */
    public function generateImage(string $type, string $prompt): string
    {
        if ($this->imageDriver === 'ollama') {
            throw new \Exception('Image generation is not supported by Ollama. Use OpenAI.');
        }

        $fullPrompt = "A high-quality, professional photograph for a premium spa website. "
            . "Subject: {$type}. Context: {$prompt}. Elegant, serene, high-end aesthetics.";

        try {
            $image = Image::of($fullPrompt)
                ->square()
                ->generate();

            // store() returns the path; for an API we need the raw content as base64
            // or the URL. The SDK returns the raw binary — store it and return the URL,
            // or return the base64-encoded content for the client to handle.
            $path = $image->storePubliclyAs("generated/{$type}-" . uniqid() . '.png');

            return asset("storage/{$path}");
        } catch (\Throwable $e) {
            Log::error("ContentGenerationService ({$this->imageDriver}): Image generation failed", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the Laravel AI SDK provider identifier for text generation.
     *
     * Returns a Lab enum for known providers, or the raw string for custom ones.
     */
    private function resolveTextProvider(): Lab|string
    {
        return match ($this->textDriver) {
            'openai' => Lab::OpenAI,
            'ollama' => 'ollama',
            default  => Lab::OpenAI,
        };
    }
}
