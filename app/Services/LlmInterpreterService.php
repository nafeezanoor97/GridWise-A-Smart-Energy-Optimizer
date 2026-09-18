<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class LlmInterpreterService
{
    public function interpret(
        array $notes,
        array $battery
    ): array {
        $prompt = $this->buildPrompt(
            $notes,
            $battery
        );

        /*
         * =====================================================
         * 1. Try Gemini first
         * =====================================================
         */

        try {
            return $this->callGemini($prompt);
        } catch (Throwable $geminiError) {

            /*
             * Gemini failed.
             *
             * Do NOT fail the whole API.
             * Move to Groq fallback.
             */

            report($geminiError);
        }

        /*
         * =====================================================
         * 2. Gemini failed -> Groq fallback
         * =====================================================
         */

        try {
            return $this->callGroq($prompt);
        } catch (Throwable $groqError) {

            report($groqError);

            throw new RuntimeException(
                'Both Gemini and Groq failed. ' .
                'Gemini error: ' . $geminiError->getMessage() .
                ' | Groq error: ' . $groqError->getMessage()
            );
        }
    }

    /*
     * =========================================================
     * Gemini
     * =========================================================
     */

    private function callGemini(
        string $prompt
    ): array {
        $apiKey =
            config('services.gemini.api_key');

        $model =
            config('services.gemini.model');

        $baseUrl =
            config('services.gemini.base_url');

        if (!$apiKey) {
            throw new RuntimeException(
                'GEMINI_API_KEY is not configured.'
            );
        }

        if (!$model) {
            throw new RuntimeException(
                'GEMINI_MODEL is not configured.'
            );
        }

        $url = rtrim($baseUrl, '/');

        $response = Http::timeout(120)
            ->retry(
                2,
                1000,
                function ($exception, $request) {
                    return $exception->response
                        && in_array(
                            $exception->response->status(),
                            [429, 500, 502, 503, 504],
                            true
                        );
                }
            )
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-goog-api-key' => $apiKey,
            ])
            ->post($url, [
                'model' => $model,
                'input' => $prompt,
                'generation_config' => [
                    'max_output_tokens' => 2000,
                ],
                'response_format' => [
                    'type' => 'text',
                    'mime_type' => 'application/json',
                ],
            ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Gemini API request failed: ' .
                $response->body()
            );
        }

        $outputText = null;

        $steps = $response->json('steps', []);

        foreach ($steps as $step) {

            if (
                ($step['type'] ?? null) === 'model_output' &&
                isset($step['content']) &&
                is_array($step['content'])
            ) {
                foreach ($step['content'] as $content) {

                    if (
                        ($content['type'] ?? null) === 'text' &&
                        isset($content['text'])
                    ) {
                        $outputText =
                            $content['text'];

                        break 2;
                    }
                }
            }
        }

        if (!$outputText) {
            throw new RuntimeException(
                'Gemini returned no usable text output.'
            );
        }

        $decoded =
            json_decode(
                $outputText,
                true
            );

        if (
            !is_array($decoded) ||
            !isset($decoded['directives']) ||
            !is_array($decoded['directives'])
        ) {
            throw new RuntimeException(
                'Gemini returned invalid directive JSON.'
            );
        }

        return $decoded['directives'];
    }

    /*
     * =========================================================
     * Groq fallback
     * =========================================================
     */

    private function callGroq(
        string $prompt
    ): array {
        $apiKey =
            config('services.groq.api_key');

        $model =
            config('services.groq.model');

        $baseUrl =
            config('services.groq.base_url');

        if (!$apiKey) {
            throw new RuntimeException(
                'GROQ_API_KEY is not configured.'
            );
        }

        if (!$model) {
            throw new RuntimeException(
                'GROQ_MODEL is not configured.'
            );
        }

        $url = rtrim($baseUrl, '/');

        $response = Http::timeout(120)
            ->withToken($apiKey)
            ->acceptJson()
            ->post($url, [
                'model' => $model,

                'messages' => [
                    [
                        'role' => 'system',
                        'content' =>
                            'You convert operator notes into strict JSON directives. ' .
                            'Return JSON only. No markdown.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],

                'temperature' => 0,

                'response_format' => [
                    'type' => 'json_object',
                ],
            ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                'Groq API request failed: ' .
                $response->body()
            );
        }

        $outputText =
            $response->json(
                'choices.0.message.content'
            );

        if (
            !is_string($outputText) ||
            trim($outputText) === ''
        ) {
            throw new RuntimeException(
                'Groq returned no usable text output.'
            );
        }

        $decoded =
            json_decode(
                $outputText,
                true
            );

        if (
            !is_array($decoded) ||
            !isset($decoded['directives']) ||
            !is_array($decoded['directives'])
        ) {
            throw new RuntimeException(
                'Groq returned invalid directive JSON.'
            );
        }

        return $decoded['directives'];
    }

    /*
     * =========================================================
     * Common prompt
     * =========================================================
     */

    private function buildPrompt(
        array $notes,
        array $battery
    ): string {
        return <<<PROMPT
You are an energy operator-note interpreter.

Your job is ONLY to convert every operator note into exactly
one structured directive.

You MUST preserve note order.

Use zero-based note_index.

Supported directive types:

1. solar_reduction

{
  "hours": [13,14],
  "factor": 0.5
}

Effective solar becomes:

original solar * factor

for those hours.

2. minimum_battery_reserve

{
  "hours": [18,19],
  "minimum_energy_kwh": 4
}

Battery energy after those hours must be at least
the larger of the base battery minimum and this reserve.

3. no_charge_window

{
  "hours": [13,14]
}

Battery charging is forbidden during those hours.

4. no_discharge_window

{
  "hours": [18,19]
}

Battery discharging is forbidden during those hours.

5. max_grid_window

{
  "hours": [18,19],
  "max_grid_kwh": 2
}

Grid usage must not exceed the specified cap.

6. no_op

For irrelevant or non-actionable notes:

{
  "note_index": 0,
  "applies": false,
  "directive_type": "no_op",
  "structured_adjustment": null,
  "explanation": "..."
}

Rules:

- Every note must produce exactly one directive.
- Preserve note order.
- note_index must be zero-based.
- Hours must be integers from 0 to 23.
- Hours must be unique and ascending.
- Use whole-hour intervals.
- Example: 1 PM to 3 PM means [13,14].
- solar_reduction factor must be between 0 and 1.
- minimum battery reserve cannot exceed battery capacity.
- max_grid_kwh cannot be negative.
- Do not invent directives that are not present in the note.
- Non-no_op directives must have applies=true.
- no_op must have applies=false.
- no_op must have structured_adjustment=null.
- Return JSON only.
- Do not use markdown.
- Do not add extra fields.

Battery capacity:
{$battery['capacity_kwh']}

Operator notes:

PROMPT . json_encode(
                $notes,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE
            ) . '

Return exactly this structure:

{
  "directives": [
    {
      "note_index": 0,
      "applies": true,
      "directive_type": "no_charge_window",
      "structured_adjustment": {
        "hours": [13,14]
      },
      "explanation": "short explanation"
    }
  ]
}';
    }
}
