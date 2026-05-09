<?php

namespace App\Ai\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * SentimentSummaryAgent
 *
 * Generates a concise (≤150 word) summary of customer feedback records
 * for the manager sentiment dashboard.
 *
 * Requirements: 11.5
 */
#[Model('gpt-4o-mini')]
#[Temperature(0.3)]
#[Timeout(12)]
class SentimentSummaryAgent implements Agent
{
    use Promptable;

    /**
     * Get the system instructions for the agent.
     */
    public function instructions(): string
    {
        return <<<PROMPT
You are a customer satisfaction analyst for a spa business. Summarize the provided
customer feedback records in 150 words or fewer.

Focus on:
- Overall sentiment trends
- Common themes across positive and negative feedback
- Any notable patterns worth the manager's attention
- Actionable observations

Be concise, professional, and actionable. Return only the summary text.
PROMPT;
    }
}
