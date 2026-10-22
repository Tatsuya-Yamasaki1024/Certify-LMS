<?php

declare(strict_types=1);

namespace App\Http\Controllers\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Http\Controllers\Controller;
use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\User;
use App\Services\AiChat\AiChatContextService;
use App\Services\AiChat\AiChatSystemPromptService;
use App\Services\AiChat\GeminiService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * 受講生向け AI チャットの会話とメッセージを管理する Controller。
 */
final class AiChatConversationController extends Controller
{
    public function __construct(
        private readonly AiChatContextService $contextService,
        private readonly AiChatSystemPromptService $systemPromptService,
        private readonly GeminiService $geminiService,
    ) {}

    /**
     * 会話一覧画面を表示する。
     */
    public function index(Request $request): View
    {
        $conversations = $request->user()
            ->aiChatConversations()
            ->with(['enrollment.certification', 'section'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        return view('ai-chat.index', [
            'conversations' => $conversations,
        ]);
    }

    /**
     * 会話画面を表示する。
     */
    public function show(Request $request, AiChatConversation $conversation): View|JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        $conversation->load([
            'enrollment.certification',
            'section.chapter.part',
            'messages' => fn ($query) => $query
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                ],
                'messages' => $conversation->messages->map(fn (AiChatMessage $message): array => [
                    'id' => $message->id,
                    'role' => $message->role->value,
                    'content' => $message->content,
                    'status' => $message->status->value,
                ])->values(),
            ]);
        }

        return view('ai-chat.show', [
            'conversation' => $conversation,
        ]);
    }

    /**
     * 指定 Section に紐づく既存会話を取得する。
     */
    public function findSectionConversation(Request $request): JsonResponse
    {
        /** @var User $student */
        $student = $request->user();

        $validated = $request->validate([
            'section_id' => ['required', 'string'],
        ]);

        $this->contextService->build($student, $validated['section_id']);

        $conversation = $student->aiChatConversations()
            ->where('section_id', $validated['section_id'])
            ->first();

        if ($conversation === null) {
            return response()->json([
                'conversation' => null,
            ]);
        }

        return response()->json([
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
            ],
        ]);
    }

    /**
     * 新しい会話を作成し、必要に応じて初回メッセージを送信する。
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $student */
        $student = $request->user();

        $validated = $request->validate([
            'source' => ['nullable', 'string', 'in:widget,full-screen'],
            'section_id' => ['nullable', 'string'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $sectionId = $validated['section_id'] ?? null;

        $contextData = $this->contextService->build(
            $student,
            $sectionId,
        );

        if ($sectionId !== null) {
            $conversation = $student->aiChatConversations()
                ->where('section_id', $sectionId)
                ->first();

            if ($conversation === null) {
                $conversation = $student->aiChatConversations()->create([
                    'enrollment_id' => $contextData['enrollment']?->id,
                    'section_id' => $sectionId,
                    'title' => '新しい会話',
                ]);
            }
        } else {
            $conversation = $student->aiChatConversations()->create([
                'enrollment_id' => $contextData['enrollment']?->id,
                'section_id' => null,
                'title' => '新しい会話',
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                ],
            ]);
        }

        $message = trim((string) ($validated['message'] ?? ''));

        if ($message !== '') {
            try {
                $this->sendMessage($request, $conversation, $message);
            } catch (HttpException $exception) {
                return redirect()
                    ->route('ai-chat.conversations.show', $conversation)
                    ->with('error', $exception->getMessage());
            }
        }

        return redirect()->route('ai-chat.conversations.show', $conversation);
    }

    /**
     * 会話にメッセージを送信し、Gemini の回答を保存する。
     */
    public function storeMessage(Request $request, AiChatConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $result = $this->sendMessage(
                $request,
                $conversation,
                trim($validated['content']),
            );

            return response()->json($result);
        } catch (HttpException $exception) {
            $upstreamStatus = $exception->getHeaders()['X-Upstream-Status'] ?? null;

            return response()->json([
                'message' => $exception->getMessage(),
                'upstream_status' => $upstreamStatus !== null
                    ? (int) $upstreamStatus
                    : null,
            ], $exception->getStatusCode());
        }
    }

    /**
     * 会話のタイトルを変更する。
     */
    public function update(Request $request, AiChatConversation $conversation): RedirectResponse|JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
        ]);

        $conversation->update([
            'title' => trim($validated['title']),
            'title_manually_edited' => true,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'conversation' => [
                    'id' => $conversation->id,
                    'title' => $conversation->title,
                ],
            ]);
        }

        return redirect()->route('ai-chat.conversations.show', $conversation);
    }

    /**
     * 会話を削除する。
     */
    public function destroy(Request $request, AiChatConversation $conversation): RedirectResponse|JsonResponse
    {
        $this->authorizeConversation($request->user(), $conversation);

        $conversation->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => '会話を削除しました。']);
        }

        return redirect()->route('ai-chat.index');
    }

    /**
     * 会話の所有者だけに操作を許可する。
     */
    private function authorizeConversation(?User $student, AiChatConversation $conversation): void
    {
        abort_unless(
            $student !== null && $conversation->user_id === $student->id,
            404,
        );
    }

    /**
     * 日次上限を確認し、Gemini に問い合わせてメッセージを保存する。
     *
     * @return array{
     *     user_message: array{id: string, role: string, content: string, status: string},
     *     assistant_message: array{id: string, role: string, content: string, status: string},
     *     conversation: array{id: string, title: string}
     * }
     */
    private function sendMessage(
        Request $request,
        AiChatConversation $conversation,
        string $content,
    ): array {
        /** @var User $student */
        $student = $request->user();

        abort_unless(config('ai-chat.enabled'), 503, 'AI チャットは現在利用できません。');

        $dailyLimit = max(1, (int) config('ai-chat.daily_message_limit', 50));

        $todayCount = AiChatMessage::query()
            ->where('role', AiChatMessageRole::User->value)
            ->where('status', AiChatMessageStatus::Completed->value)
            ->whereHas('conversation', fn ($query) => $query->where('user_id', $student->id))
            ->where('created_at', '>=', now()->startOfDay())
            ->where('created_at', '<', now()->addDay()->startOfDay())
            ->count();

        if ($todayCount >= $dailyLimit) {
            abort(429, '本日の AI チャット利用上限に達しました。');
        }

        $userMessage = DB::transaction(function () use ($conversation, $content): AiChatMessage {
            $message = $conversation->messages()->create([
                'role' => AiChatMessageRole::User,
                'status' => AiChatMessageStatus::Pending,
                'content' => $content,
            ]);

            $conversation->update([
                'last_message_at' => now(),
            ]);

            return $message;
        });

        $historyLimit = max(1, (int) config('ai-chat.history_limit', 20));

        $history = $conversation->messages()
            ->where(function ($query) use ($userMessage): void {
                $query->where('status', AiChatMessageStatus::Completed->value)
                    ->orWhere('id', $userMessage->id);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($historyLimit)
            ->get()
            ->reverse()
            ->values();

        $contents = $history->map(fn (AiChatMessage $message): array => [
            'role' => $message->role === AiChatMessageRole::Assistant ? 'model' : 'user',
            'parts' => [
                ['text' => $message->content],
            ],
        ])->all();

        $contextData = $this->contextService->build(
            $student,
            $conversation->section_id,
        );

        $systemInstruction = $this->systemPromptService->build($contextData['context']);

        try {
            $answer = $this->geminiService->generate($systemInstruction, $contents);
        } catch (Throwable $exception) {
            $userMessage->update([
                'status' => AiChatMessageStatus::Error,
                'error_detail' => $exception::class,
            ]);

            Log::channel('ai-chat')->error('AI チャットの回答生成に失敗しました。', [
                'conversation_id' => $conversation->id,
                'user_id' => $student->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $headers = $exception instanceof RequestException
                ? ['X-Upstream-Status' => (string) $exception->response->status()]
                : [];

            $apiKey = config('ai-chat.gemini.api_key');
            $apiKeyMissing = ! is_string($apiKey) || $apiKey === '';

            throw new HttpException(
                $apiKeyMissing ? 503 : 502,
                $apiKeyMissing
                    ? 'AIチャットは現在利用できません。管理者にお問い合わせください。'
                    : 'AI の回答を取得できませんでした。時間をおいて再度お試しください。',
                $exception,
                $headers,
            );
        }

        $assistantMessage = DB::transaction(function () use ($conversation, $answer, $userMessage): AiChatMessage {
            $userMessage->update([
                'status' => AiChatMessageStatus::Completed,
            ]);

            $message = $conversation->messages()->create([
                'role' => AiChatMessageRole::Assistant,
                'status' => AiChatMessageStatus::Completed,
                'content' => $answer['content'],
                'model' => $answer['model'],
                'response_time_ms' => $answer['response_time_ms'],
                'input_tokens' => $answer['input_tokens'],
                'output_tokens' => $answer['output_tokens'],
            ]);

            $conversation->update([
                'last_message_at' => now(),
            ]);

            return $message;
        });

        if (
            config('ai-chat.auto_title.enabled')
            && ! $conversation->fresh()->title_manually_edited
        ) {
            try {
                $titleHistory = $conversation->messages()
                    ->where('status', AiChatMessageStatus::Completed->value)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit($historyLimit)
                    ->get()
                    ->reverse()
                    ->values();

                $titleContents = $titleHistory->map(
                    fn (AiChatMessage $message): array => [
                        'role' => $message->role === AiChatMessageRole::Assistant ? 'model' : 'user',
                        'parts' => [
                            ['text' => $message->content],
                        ],
                    ]
                )->all();

                $title = $this->geminiService->generateTitle($titleContents);

                AiChatConversation::query()
                    ->whereKey($conversation->id)
                    ->where('title_manually_edited', false)
                    ->update([
                        'title' => $title,
                    ]);
            } catch (Throwable $exception) {
                Log::channel('ai-chat')->warning('AI チャットのタイトル生成に失敗しました。', [
                    'conversation_id' => $conversation->id,
                    'user_id' => $student->id,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'user_message' => [
                'id' => $userMessage->id,
                'role' => $userMessage->role->value,
                'content' => $userMessage->content,
                'status' => $userMessage->status->value,
            ],
            'assistant_message' => [
                'id' => $assistantMessage->id,
                'role' => $assistantMessage->role->value,
                'content' => $assistantMessage->content,
                'status' => $assistantMessage->status->value,
            ],
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->fresh()->title,
            ],
        ];
    }
}
