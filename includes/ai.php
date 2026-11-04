<?php
/**
 * Optional AI content assistant. Provider keys are read server-side only and
 * are never returned to the browser.
 */

function ai_providers(): array
{
    return db_all('SELECT * FROM ai_providers ORDER BY priority ASC, id ASC');
}

function ai_active_provider(): ?array
{
    foreach (ai_providers() as $provider) {
        if ($provider['status'] !== 'active') {
            continue;
        }
        $key = decrypt_secret($provider['api_key'] ?? null) ?: (string) config('ai.' . $provider['slug'] . '.key', '');
        if ($key !== '') {
            $provider['_key'] = $key;
            return $provider;
        }
    }
    return null;
}

function ai_available(): bool
{
    return ai_active_provider() !== null;
}

function ai_monthly_usage(int $businessId): int
{
    return (int) db_val(
        'SELECT COUNT(*) FROM ai_usage WHERE business_id = ? AND created_at > (NOW() - INTERVAL 30 DAY)',
        [$businessId]
    );
}

function ai_limit_for_business(array $business): int
{
    $limits = business_limits($business);
    return (int) ($limits['ai_credits'] ?? 20);
}

function ai_prompt_for(string $purpose, array $context): string
{
    $business = $context['business'] ?? [];
    $name = $business['name'] ?? 'the business';
    $category = $business['category_name'] ?? 'business';
    $city = $business['city'] ?? '';
    $description = $business['description'] ?? '';
    return match ($purpose) {
        'description' => "Write a professional, specific business description for a {$category} called {$name}" . ($city ? " in {$city}" : '') . ". Existing notes: {$description}. 80-120 words, no clichés, no invented awards or statistics.",
        'hero' => "Write 3 short homepage headlines (max 9 words) and one supporting sentence for {$name}, a {$category}" . ($city ? " in {$city}" : '') . ". Return each headline on a new line, then the supporting sentence.",
        'about' => "Write an About section for {$name}, a {$category}" . ($city ? " in {$city}" : '') . ". 120-160 words, warm and credible. Do not invent facts, numbers, certifications or awards.",
        'product' => "Write a concise product description for \"{$context['product']}\" sold by {$name}. 45-70 words. No fabricated specifications.",
        'service' => "Write a concise service description for \"{$context['service']}\" offered by {$name}, a {$category}. 50-80 words. No fabricated claims.",
        'seo' => "Create an SEO title (max 60 characters) and meta description (max 155 characters) for {$name}, a {$category}" . ($city ? " in {$city}" : '') . ". Format as: TITLE: ...\\nDESCRIPTION: ...",
        'faq' => "Write 5 useful FAQ questions and answers for a {$category} called {$name}. Keep answers factual and under 45 words. Do not invent policies, prices or guarantees.",
        'sections' => "Suggest the 6 best homepage sections for a {$category} website called {$name}. Return a numbered list with one short reason each.",
        default => (string) ($context['prompt'] ?? ''),
    };
}

function ai_generate(string $purpose, array $business, array $context = []): array
{
    $provider = ai_active_provider();
    if (!$provider) {
        return ['ok' => false, 'error' => 'AI is not configured. Add a provider key in Admin → Settings.'];
    }
    $businessId = (int) $business['id'];
    $used = ai_monthly_usage($businessId);
    $limit = ai_limit_for_business($business);
    if ($used >= $limit) {
        return ['ok' => false, 'error' => 'Your AI content limit has been reached for this billing period.'];
    }
    $prompt = ai_prompt_for($purpose, ['business' => $business] + $context);
    $slug = $provider['slug'];
    $model = $provider['model'] ?: (string) config('ai.' . $slug . '.model', '');
    $key = $provider['_key'];
    $tokensIn = 0;
    $tokensOut = 0;

    if ($slug === 'openai' || $slug === 'groq') {
        $base = $slug === 'groq' ? 'https://api.groq.com/openai/v1/chat/completions' : 'https://api.openai.com/v1/chat/completions';
        $response = http_request('POST', $base, [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ], [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a careful business copywriter. Never invent facts, statistics, certifications or guarantees. Reply with the requested content only.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.6,
            'max_tokens' => 700,
        ], 45);
        $text = $response['body']['choices'][0]['message']['content'] ?? null;
        $tokensIn = (int) ($response['body']['usage']['prompt_tokens'] ?? 0);
        $tokensOut = (int) ($response['body']['usage']['completion_tokens'] ?? 0);
    } elseif ($slug === 'gemini') {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . rawurlencode($key);
        $response = http_request('POST', $url, ['Content-Type: application/json'], [
            'contents' => [['parts' => [['text' => $prompt]]]],
            'generationConfig' => ['temperature' => 0.6, 'maxOutputTokens' => 700],
        ], 45);
        $text = $response['body']['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $tokensIn = (int) ($response['body']['usageMetadata']['promptTokenCount'] ?? 0);
        $tokensOut = (int) ($response['body']['usageMetadata']['candidatesTokenCount'] ?? 0);
    } else {
        return ['ok' => false, 'error' => 'Unsupported AI provider.'];
    }

    if (!$text) {
        $error = $response['body']['error']['message'] ?? $response['error'] ?? 'The AI provider did not return content.';
        return ['ok' => false, 'error' => $error];
    }
    db_insert('ai_usage', [
        'business_id' => $businessId,
        'user_id' => current_user()['id'] ?? null,
        'provider' => $slug,
        'model' => $model,
        'purpose' => $purpose,
        'tokens_in' => $tokensIn,
        'tokens_out' => $tokensOut,
    ]);
    return ['ok' => true, 'text' => trim($text), 'provider' => $slug, 'remaining' => max(0, $limit - $used - 1)];
}
