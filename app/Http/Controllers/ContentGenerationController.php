<?php

namespace App\Http\Controllers;

use App\Services\ContentGenerationService;
use Illuminate\Http\Request;

class ContentGenerationController extends Controller
{
    protected ContentGenerationService $service;

    public function __construct(ContentGenerationService $service)
    {
        $this->service = $service;
    }

    public function generateDescription(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'fields' => 'required|array',
        ]);

        try {
            $description = $this->service->generateDescription(
                $request->type,
                $request->fields
            );

            return response()->json(['description' => $description]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function generateImage(Request $request)
    {
        $request->validate([
            'type' => 'required|string',
            'prompt' => 'required|string',
        ]);

        try {
            $imageUrl = $this->service->generateImage(
                $request->type,
                $request->prompt
            );

            return response()->json(['image_url' => $imageUrl]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
