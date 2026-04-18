<?php

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Services\AITranslationService;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ChatbotController
 *
 * Handles natural-language chat for both customer booking assistant
 * and staff operational query flows.
 *
 * Requirements: 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 8.1, 8.5
 */
class ChatbotController extends Controller
{
    use ResolvesLocale;

    public function __construct(
        private readonly ChatbotService $service,
        private readonly AITranslationService $translator,
    ) {}

    /**
     * POST /api/ai/chat
     *
     * Customer booking assistant endpoint (SpaBooking).
     *
     * Requirements: 4.2, 4.3, 4.4, 4.5, 4.6, 4.7
     */
    public function customer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message'    => 'required|string|max:2000',
            'session_id' => 'nullable|string',
        ]);

        $user = $request->user();

        // Load or create a ChatSession for context retention (Requirement 4.7)
        $chatSession = ChatSession::firstOrCreate(
            ['user_id' => $user->id, 'user_type' => 'customer'],
            ['messages' => []]
        );

        $history = $chatSession->messages ?? [];

        $response = $this->service->processCustomerMessage($validated['message'], $history);

        // Append the new exchange to history (keep last 10 messages)
        $history[] = ['role' => 'user',      'content' => $validated['message']];
        $history[] = ['role' => 'assistant', 'content' => json_encode($response)];
        $chatSession->update(['messages' => array_slice($history, -10)]);

        // Translate AI-generated text fields for the requested locale
        $locale = $this->resolveLocale($request);
        $response = $this->translateResponse($response, $locale);

        return response()->json($response);
    }

    /**
     * POST /api/ai/chat/staff
     *
     * Staff operational query endpoint (SpaCashier).
     *
     * Requirements: 5.2, 5.3, 5.4, 5.5, 5.6, 5.7
     */
    public function staff(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:2000',
        ]);

        $user     = $request->user();
        $employee = $user->employee;

        $staffContext = [
            'role'      => strtolower($user->type ?? 'staff'),
            'branch_id' => $employee?->branch_id,
        ];

        $response = $this->service->processStaffQuery($validated['query'], $staffContext);

        // Translate AI-generated text fields for the requested locale
        $locale = $this->resolveLocale($request);
        $response = $this->translateResponse($response, $locale);

        return response()->json($response);
    }

    /**
     * Translate user-facing text fields in a chatbot response.
     */
    private function translateResponse(array $response, string $locale): array
    {
        if (isset($response['formattedAnswer'])) {
            $response['formattedAnswer'] = $this->translator->translate($response['formattedAnswer'], $locale);
        }
        if (isset($response['message'])) {
            $response['message'] = $this->translator->translate($response['message'], $locale);
        }
        return $response;
    }
}
