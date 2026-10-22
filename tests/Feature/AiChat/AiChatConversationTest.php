<?php

declare(strict_types=1);

namespace Tests\Feature\AiChat;

use App\Enums\AiChatMessageRole;
use App\Enums\AiChatMessageStatus;
use App\Models\AiChatConversation;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AI チャットの会話・メッセージ処理を検証する。
 */
class AiChatConversationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai-chat.enabled' => true,
            'ai-chat.gemini.api_key' => 'test-api-key',
            'ai-chat.gemini.model' => 'gemini-2.5-flash-lite',
            'ai-chat.daily_message_limit' => 50,
            'ai-chat.history_limit' => 20,
            'ai-chat.auto_title.enabled' => true,
        ]);
    }

    /**
     * Gemini の回答を保存し、AI が生成したタイトルを会話に設定する。
     */
    // 回答生成とタイトル生成を別々に呼び出し、生成されたタイトルが保存されることを確認する。
    public function test_message_is_sent_to_gemini_and_conversation_is_auto_titled(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => '回答です。'],
                                ],
                            ],
                        ],
                    ],
                    'usageMetadata' => [
                        'promptTokenCount' => 10,
                        'candidatesTokenCount' => 5,
                    ],
                ])
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'Laravelのルーティング'],
                                ],
                            ],
                        ],
                    ],
                ]),
        ]);

        $student = User::factory()->student()->inProgress()->create();

        $conversation = AiChatConversation::query()->create([
            'user_id' => $student->id,
            'title' => '新しい会話',
        ]);

        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => 'Laravelのルーティングについて教えてください'],
        );

        $response->assertOk()
            ->assertJsonPath('user_message.content', 'Laravelのルーティングについて教えてください')
            ->assertJsonPath('assistant_message.content', '回答です。')
            ->assertJsonPath('conversation.title', 'Laravelのルーティング');

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User->value,
            'content' => 'Laravelのルーティングについて教えてください',
        ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'content' => '回答です。',
            'model' => 'gemini-2.5-flash-lite',
            'input_tokens' => 10,
            'output_tokens' => 5,
        ]);

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversation->id,
            'title' => 'Laravelのルーティング',
            'title_manually_edited' => false,
        ]);

        Http::assertSentCount(2);

        Http::assertSent(
            fn (Request $request): bool => str_contains($request->url(), 'gemini-2.5-flash-lite:generateContent')
                && $request['contents'][0]['role'] === 'user'
                && $request['contents'][0]['parts'][0]['text'] === 'Laravelのルーティングについて教えてください'
        );

        Http::assertSent(
            fn (Request $request): bool => str_contains(
                $request['systemInstruction']['parts'][0]['text'] ?? '',
                'タイトル生成担当'
            )
        );
    }

    /**
     * 設定した件数を超える会話履歴を Gemini に送信しない。
     */
    public function test_only_configured_number_of_messages_is_sent_as_history(): void
    {
        config(['ai-chat.history_limit' => 3]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '回答です。'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::query()->create([
            'user_id' => $student->id,
            'title' => '履歴テスト',
        ]);

        foreach (
            [
                [AiChatMessageRole::User, '以前の質問'],
                [AiChatMessageRole::Assistant, '以前の回答'],
            ] as [$role, $content]
        ) {
            $conversation->messages()->create([
                'role' => $role,
                'status' => AiChatMessageStatus::Completed,
                'content' => $content,
            ]);
        }

        $this->actingAs($student)->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => '今回の質問'],
        )->assertOk();

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), ':generateContent')) {
                return false;
            }

            $contents = $request['contents'];

            return count($contents) === 3
                && $contents[0]['role'] === 'user'
                && $contents[0]['parts'][0]['text'] === '以前の質問'
                && $contents[1]['role'] === 'model'
                && $contents[1]['parts'][0]['text'] === '以前の回答'
                && $contents[2]['role'] === 'user'
                && $contents[2]['parts'][0]['text'] === '今回の質問';
        });
    }

    /**
     * 日次上限に達している場合は 429 を返し、Gemini に問い合わせない。
     */
    public function test_daily_message_limit_returns_429_without_calling_gemini(): void
    {
        config(['ai-chat.daily_message_limit' => 1]);
        Http::fake();

        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::query()->create([
            'user_id' => $student->id,
            'title' => '上限テスト',
        ]);

        $conversation->messages()->create([
            'role' => AiChatMessageRole::User,
            'status' => AiChatMessageStatus::Completed,
            'content' => '本日の質問',
        ]);

        $this->actingAs($student)->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => '上限到達後の質問'],
        )->assertStatus(429);

        Http::assertNothingSent();
    }

    /**
     * Gemini API が失敗した場合は 502 を返す。
     */
    public function test_gemini_api_failure_returns_502(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'upstream error'],
            ], 500),
        ]);

        $student = User::factory()->student()->inProgress()->create();
        $conversation = AiChatConversation::query()->create([
            'user_id' => $student->id,
            'title' => 'APIエラーテスト',
        ]);

        // Gemini API のエラーをアプリ側の 502 と上流のステータスに分けて返す。
        $this->actingAs($student)->postJson(
            route('ai-chat.conversations.messages.store', $conversation),
            ['content' => '質問です'],
        )->assertStatus(502)
            ->assertJsonPath('message', 'AI の回答を取得できませんでした。時間をおいて再度お試しください。')
            ->assertJsonPath('upstream_status', 500);
    }

    /**
     * Gemini API の失敗時は質問を Error にし、日次上限にカウントしない。
     */
    // Gemini API の失敗後に再送信でき、成功した質問だけが日次上限にカウントされることを確認する。
    public function test_failed_message_does_not_count_toward_daily_limit(): void
    {
        config(['ai-chat.daily_message_limit' => 1]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'upstream error']], 500)
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => '再送信への回答です。'],
                                ],
                            ],
                        ],
                    ],
                ]),
        ]);

        $student = User::factory()->student()->inProgress()->create();

        $conversation = AiChatConversation::query()->create([
            'user_id' => $student->id,
            'title' => '再送信テスト',
        ]);

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '最初の質問'],
            )
            ->assertStatus(502);

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User->value,
            'content' => '最初の質問',
            'status' => AiChatMessageStatus::Error->value,
        ]);

        $this->assertSame(
            0,
            $student->aiChatConversations()
                ->withCount([
                    'messages as completed_user_messages_count' => fn ($query) => $query
                        ->where('role', AiChatMessageRole::User->value)
                        ->where('status', AiChatMessageStatus::Completed->value)
                        ->where('created_at', '>=', now()->startOfDay())
                        ->where('created_at', '<', now()->addDay()->startOfDay()),
                ])
                ->get()
                ->sum('completed_user_messages_count'),
        );

        $this->actingAs($student)
            ->postJson(
                route('ai-chat.conversations.messages.store', $conversation),
                ['content' => '再送信する質問'],
            )
            ->assertOk()
            ->assertJsonPath('assistant_message.content', '再送信への回答です。');

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::User->value,
            'content' => '再送信する質問',
            'status' => AiChatMessageStatus::Completed->value,
        ]);

        $this->assertDatabaseHas('ai_chat_messages', [
            'conversation_id' => $conversation->id,
            'role' => AiChatMessageRole::Assistant->value,
            'content' => '再送信への回答です。',
            'status' => AiChatMessageStatus::Completed->value,
        ]);
    }

    /**
     * 他の受講生の会話履歴を参照できない。
     */
    public function test_student_cannot_view_another_students_conversation(): void
    {
        $owner = User::factory()->student()->inProgress()->create();
        $anotherStudent = User::factory()->student()->inProgress()->create();

        $conversation = AiChatConversation::query()->create([
            'user_id' => $owner->id,
            'title' => '他人の会話',
        ]);

        $this->actingAs($anotherStudent)
            ->getJson(route('ai-chat.conversations.show', $conversation))
            ->assertNotFound();
    }

    /**
     * 初回メッセージの送信に失敗した場合は会話画面にエラーを表示する。
     */
    // 新規会話の初回送信が失敗した場合にエラーメッセージを表示する。
    public function test_initial_message_failure_displays_error_on_conversation_page(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => ['message' => 'upstream error'],
            ], 500),
        ]);

        $student = User::factory()->student()->inProgress()->create();

        $response = $this->actingAs($student)->post(
            route('ai-chat.conversations.store'),
            [
                'source' => 'full-screen',
                'message' => '最初の質問です',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHas(
            'error',
            'AI の回答を取得できませんでした。時間をおいて再度お試しください。',
        );

        $conversation = AiChatConversation::query()
            ->where('user_id', $student->id)
            ->latest('created_at')
            ->firstOrFail();

        $this->get(route('ai-chat.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('AI の回答を取得できませんでした。時間をおいて再度お試しください。');
    }

    /**
     * デフォルト資格から作成した会話に受講登録を保存する。
     */
    // デフォルト資格の受講登録が会話に正しく紐づくことを確認する。
    public function test_default_enrollment_is_saved_when_creating_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
        ]);
        $student->update(['default_enrollment_id' => $enrollment->id]);

        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.store'),
            ['source' => 'full-screen'],
        );

        $response->assertOk();

        $conversationId = $response->json('conversation.id');

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversationId,
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => null,
        ]);
    }

    /**
     * Section から作成した会話に対象資格の受講登録を保存する。
     */
    // Section が属する資格の受講登録が会話に正しく紐づくことを確認する。
    public function test_section_enrollment_is_saved_when_creating_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $part = Part::factory()->published()
            ->forCertification($certification)
            ->create();
        $chapter = Chapter::factory()->published()
            ->forPart($part)
            ->create();
        $section = Section::factory()->published()
            ->forChapter($chapter)
            ->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.store'),
            [
                'source' => 'widget',
                'section_id' => $section->id,
            ],
        );

        $response->assertOk();

        $conversationId = $response->json('conversation.id');

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $conversationId,
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
        ]);
    }

    /**
     * 同じ Section からの再起動時に既存の会話を再利用する。
     */
    // 同じ Section から再度起動しても会話が重複作成されないことを確認する。
    public function test_existing_section_conversation_is_reused(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $part = Part::factory()->published()
            ->forCertification($certification)
            ->create();
        $chapter = Chapter::factory()->published()
            ->forPart($part)
            ->create();
        $section = Section::factory()->published()
            ->forChapter($chapter)
            ->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $payload = [
            'source' => 'widget',
            'section_id' => $section->id,
        ];

        $firstResponse = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.store'),
            $payload,
        );
        $firstResponse->assertOk();

        $secondResponse = $this->postJson(
            route('ai-chat.conversations.store'),
            $payload,
        );
        $secondResponse->assertOk();

        $this->assertSame(
            $firstResponse->json('conversation.id'),
            $secondResponse->json('conversation.id'),
        );

        $this->assertDatabaseCount('ai_chat_conversations', 1);

        $this->assertDatabaseHas('ai_chat_conversations', [
            'id' => $firstResponse->json('conversation.id'),
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
        ]);
    }

    /**
     * 教材画面から送信した場合、資格名と教材階層だけをシステムプロンプトに含める。
     */
    // 教材画面から起動した際のGemini向けコンテキストを確認する。
    public function test_section_context_is_included_in_gemini_system_instruction(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '回答です。'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create([
            'name' => 'テスト資格',
        ]);
        $part = Part::factory()->published()
            ->forCertification($certification)
            ->create(['title' => '基礎編', 'order' => 2]);
        $chapter = Chapter::factory()->published()
            ->forPart($part)
            ->create(['title' => '第1章', 'order' => 3]);
        $section = Section::factory()->published()
            ->forChapter($chapter)
            ->create(['title' => 'ルーティング', 'order' => 4]);

        Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $conversationResponse = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.store'),
            [
                'source' => 'widget',
                'section_id' => $section->id,
            ],
        );
        $conversationResponse->assertOk();

        $conversationId = $conversationResponse->json('conversation.id');

        $this->postJson(
            route('ai-chat.conversations.messages.store', $conversationId),
            ['content' => '質問です'],
        )->assertOk();

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), ':generateContent')) {
                return false;
            }

            $instruction = $request['systemInstruction']['parts'][0]['text'] ?? '';

            $contextMarker = '以下は現在の会話に関するコンテキストです。';
            $contextPosition = strpos($instruction, $contextMarker);

            if ($contextPosition === false) {
                return false;
            }

            $actualContext = substr(
                $instruction,
                $contextPosition + strlen($contextMarker)
            );

            $expectedLines = [
                '受講資格: テスト資格',
                '現在の教材:',
                'Part 2: 基礎編',
                'Chapter 3: 第1章',
                'Section 4: ルーティング',
            ];

            $previousPosition = -1;

            foreach ($expectedLines as $line) {
                $position = strpos($actualContext, $line);

                if ($position === false || $position <= $previousPosition) {
                    return false;
                }

                $previousPosition = $position;
            }

            return true;
        });
    }

    /**
     * 指定 Section に既存の会話がある場合、その会話を取得できる。
     */
    // 同じ Section に紐づく既存会話を検索できることを確認する。
    public function test_existing_section_conversation_can_be_found(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $part = Part::factory()->published()
            ->forCertification($certification)
            ->create();
        $chapter = Chapter::factory()->published()
            ->forPart($part)
            ->create();
        $section = Section::factory()->published()
            ->forChapter($chapter)
            ->create();
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $conversation = AiChatConversation::query()->create([
            'user_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'section_id' => $section->id,
            'title' => '既存の相談',
        ]);

        $response = $this->actingAs($student)->getJson(
            route('ai-chat.conversations.section', [
                'section_id' => $section->id,
            ]),
        );

        $response->assertOk()
            ->assertJsonPath('conversation.id', $conversation->id)
            ->assertJsonPath('conversation.title', '既存の相談');

        $this->assertDatabaseCount('ai_chat_conversations', 1);
    }

    /**
     * 指定 Section に既存の会話がない場合、会話を新規作成せず null を返す。
     */
    // 既存会話がない場合に空の会話が作成されないことを確認する。
    public function test_section_conversation_search_returns_null_when_not_found(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $part = Part::factory()->published()
            ->forCertification($certification)
            ->create();
        $chapter = Chapter::factory()->published()
            ->forPart($part)
            ->create();
        $section = Section::factory()->published()
            ->forChapter($chapter)
            ->create();

        Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);

        $response = $this->actingAs($student)->getJson(
            route('ai-chat.conversations.section', [
                'section_id' => $section->id,
            ]),
        );

        $response->assertOk()
            ->assertExactJson([
                'conversation' => null,
            ]);

        $this->assertDatabaseCount('ai_chat_conversations', 0);
    }

    /**
     * 教材画面以外から送信した場合、デフォルト資格名だけをコンテキストに含める。
     */
    // 教材画面以外ではデフォルト資格名だけがGeminiへ渡ることを確認する。
    public function test_default_enrollment_context_contains_only_certification_name(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '回答です。'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create([
            'name' => 'デフォルト資格',
        ]);
        $enrollment = Enrollment::factory()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);
        $student->update(['default_enrollment_id' => $enrollment->id]);

        $conversationResponse = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.store'),
            ['source' => 'full-screen'],
        );
        $conversationResponse->assertOk();

        $conversationId = $conversationResponse->json('conversation.id');

        $this->postJson(
            route('ai-chat.conversations.messages.store', $conversationId),
            ['content' => '質問です'],
        )->assertOk();

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), ':generateContent')) {
                return false;
            }

            $instruction = $request['systemInstruction']['parts'][0]['text'] ?? '';

            return str_contains($instruction, '受講資格: デフォルト資格')
                && ! str_contains($instruction, '現在の教材:')
                && ! str_contains($instruction, 'Part ')
                && ! str_contains($instruction, 'Chapter ')
                && ! str_contains($instruction, 'Section ');
        });
    }

    /**
     * デフォルト資格がない場合、資格や教材のコンテキストを含めない。
     */
    // デフォルト資格がない場合にコンテキストが付加されないことを確認する。
    public function test_no_default_enrollment_omits_context_from_gemini_system_instruction(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '回答です。'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $student = User::factory()->student()->inProgress()->create();

        $conversationResponse = $this->actingAs($student)->postJson(
            route('ai-chat.conversations.store'),
            ['source' => 'full-screen'],
        );
        $conversationResponse->assertOk();

        $conversationId = $conversationResponse->json('conversation.id');

        $this->postJson(
            route('ai-chat.conversations.messages.store', $conversationId),
            ['content' => '質問です'],
        )->assertOk();

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), ':generateContent')) {
                return false;
            }

            $instruction = $request['systemInstruction']['parts'][0]['text'] ?? '';

            return ! str_contains($instruction, '受講資格:')
                && ! str_contains($instruction, '現在の教材:')
                && ! str_contains($instruction, 'Part ')
                && ! str_contains($instruction, 'Chapter ')
                && ! str_contains($instruction, 'Section ');
        });
    }

    /**
     * 受講資格のない Section から会話を作成できない。
     */
    // 対象資格の受講登録がない受講生のアクセスを拒否する。
    public function test_student_without_enrollment_cannot_create_section_conversation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $part = Part::factory()->published()
            ->forCertification($certification)
            ->create();
        $chapter = Chapter::factory()->published()
            ->forPart($part)
            ->create();
        $section = Section::factory()->published()
            ->forChapter($chapter)
            ->create();

        $this->actingAs($student)->postJson(
            route('ai-chat.conversations.store'),
            [
                'source' => 'widget',
                'section_id' => $section->id,
            ],
        )->assertForbidden();

        $this->assertDatabaseCount('ai_chat_conversations', 0);
    }
}
