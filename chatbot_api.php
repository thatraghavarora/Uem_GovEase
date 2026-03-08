<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

if (is_readable(__DIR__ . '/knowledge.php')) {
    require __DIR__ . '/knowledge.php';
}
if (!isset($knowledge) || !is_array($knowledge)) {
    $knowledge = [];
}

$message = trim((string) ($_POST['message'] ?? ''));
if ($message === '') {
    echo 'Please type a question.';
    exit;
}

$context = '';
foreach ($knowledge as $key => $value) {
    if (stripos($message, $key) !== false) {
        $context .= trim($value) . "\n";
    }
}

if ($context === '') {
    $context = 'General GovEase platform information.';
}

$prompt = "You are GovEase AI Assistant.\n\n"
    . "Context Information:\n"
    . $context . "\n\n"
    . "User Question:\n"
    . $message . "\n\n"
    . "Answer the user clearly and helpfully.\n"
    . "If the question is unrelated to GovEase, events, or services,\n"
    . "respond conversationally in a friendly way.\n";

$apiKey = getenv('GEMINI_API_KEY') ?: 'AIzaSyDWhh11Yxf5rB4QWMvUvwS2Ry9FXQ5JZ8k';
$reply = '';
if (function_exists('curl_init')) {
    $reply = fetchGeminiResponse($apiKey, $prompt);
}

if ($reply === '') {
    $reply = buildLocalAnswer($message, $context);
}

echo $reply;

function fetchGeminiResponse(string $apiKey, string $prompt): string
{
    if ($apiKey === '' || !function_exists('curl_init')) {
        return '';
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key='
        . rawurlencode($apiKey);

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                ],
            ],
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 15,
    ]);

    $raw = curl_exec($ch);
    if ($raw === false) {
        curl_close($ch);
        return '';
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status >= 400) {
        return '';
    }

    $result = json_decode($raw, true);
    if (!is_array($result)) {
        return '';
    }

    return (string) ($result['candidates'][0]['content']['parts'][0]['text'] ?? '');
}

function buildLocalAnswer(string $message, string $context): string
{
    $message = strtolower($message);
    if (str_contains($message, 'token')) {
        return "Tokens are generated when you book an appointment. "
            . "You can view your token status and estimated wait time in the app.";
    }
    if (str_contains($message, 'appointment')) {
        return "Appointments let you avoid long queues. "
            . "Choose a center, pick a time, and get a token instantly.";
    }
    if (trim($context) !== '') {
        return "Here is what I found:\n" . trim($context);
    }

    return "I can help with bookings, tokens, and center details. "
        . "Ask me about appointments, tokens, or services.";
}
