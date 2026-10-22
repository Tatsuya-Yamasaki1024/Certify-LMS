<?php

declare(strict_types=1);

namespace App\Services\AiChat;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gemini API との通信を担当するサービス。
 */
class GeminiService
{
    private const API_ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

    private const TIMEOUT_SECONDS = 30;

    /**
     * Gemini API に会話履歴を送信し、回答と使用量を取得する。
     *
     * @param array<int, array{role: string, parts: array<int, array{text: string}>}> $contents
     *
     * @return array{
     *     content: string,
     *     model: string,
     *     response_time_ms: int,
     *     input_tokens: int|null,
     *     output_tokens: int|null
     * }
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws RuntimeException
     */
    public function generate(string $systemInstruction, array $contents): array
    {
        $apiKey = config('ai-chat.gemini.api_key');
        $model = (string) config('ai-chat.gemini.model', 'gemini-2.5-flash-lite');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Gemini API キーが設定されていません。');
        }

        $url = self::API_ENDPOINT.rawurlencode($model).':generateContent';

        $startedAt = hrtime(true);

        $response = Http::acceptJson()
            ->withQueryParameters(['key' => $apiKey])
            ->timeout(self::TIMEOUT_SECONDS)
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemInstruction],
                    ],
                ],
                'contents' => $contents,
            ]);

        $response->throw();

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Gemini API のレスポンス形式が不正です。');
        }

        $parts = $data['candidates'][0]['content']['parts'] ?? null;

        if (! is_array($parts)) {
            throw new RuntimeException('Gemini API から回答を取得できませんでした。');
        }

        $text = collect($parts)
            ->pluck('text')
            ->filter(fn (mixed $part): bool => is_string($part))
            ->implode('');

        if ($text === '') {
            throw new RuntimeException('Gemini API の回答が空でした。');
        }

        $usage = $data['usageMetadata'] ?? [];

        return [
            'content' => $text,
            'model' => $model,
            'response_time_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
            'input_tokens' => isset($usage['promptTokenCount'])
                ? (int) $usage['promptTokenCount']
                : null,
            'output_tokens' => isset($usage['candidatesTokenCount'])
                ? (int) $usage['candidatesTokenCount']
                : null,
        ];
    }

    /**
     * 会話内容からタイトルを生成する。
     *
     * @param array<int, array{role: string, parts: array<int, array{text: string}>}> $contents
     */
    public function generateTitle(array $contents): string
    {
        $result = $this->generate(
            'あなたは学習相談チャットのタイトル生成担当です。'
                .'会話の主題を表す簡潔な日本語タイトルを40文字以内で作成してください。'
                .'タイトルのみを返し、説明・引用符・改行は含めないでください。',
            $contents,
        );

        $title = trim($result['content']);
        $title = trim($title, " \t\n\r\0\x0B\"'「」");

        $title = mb_substr($title, 0, 40);

        if ($title === '') {
            throw new RuntimeException('Gemini API からタイトルを取得できませんでした。');
        }

        return $title;
    }
}
