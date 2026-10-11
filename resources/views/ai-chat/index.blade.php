@extends('layouts.app')

@section('title', 'AI 相談')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'AI 相談'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-display font-bold tracking-tight text-ink-900">AI 相談</h1>
        <p class="text-sm text-ink-500 mt-1">教材で詰まった時の補助線。AI の回答は参考情報です。</p>
    </div>

    @if ($conversations->isEmpty())
        <div class="mt-6">
            <x-card padding="lg">
                <x-empty-state
                    icon="sparkles"
                    title="まだ相談履歴はありません"
                    description="気になる教材があれば、教材画面右下の AI ボタンから直接相談できます。"
                >
                    <x-slot:action>
                        <x-button variant="primary" data-modal-trigger="new-ai-chat-modal">
                            最初の相談を始める
                        </x-button>
                    </x-slot:action>
                </x-empty-state>
            </x-card>
        </div>
    @else
        <div class="mt-6 flex items-center justify-between">
            <h2 class="font-display font-bold text-lg text-ink-900">相談履歴</h2>
            <x-button variant="primary" data-modal-trigger="new-ai-chat-modal">
                新しい会話
            </x-button>
        </div>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($conversations as $conversation)
                <a
                    href="{{ route('ai-chat.conversations.show', $conversation) }}"
                    class="block rounded-2xl border border-subtle bg-surface-raised p-5 hover:border-secondary-400 transition"
                >
                    <h3 class="font-semibold text-ink-900 line-clamp-2">
                        {{ $conversation->title }}
                    </h3>

                    <p class="mt-2 text-sm text-ink-500">
                        @if ($conversation->section?->title)
                            {{ $conversation->section->title }}
                        @elseif ($conversation->enrollment?->certification?->name)
                            {{ $conversation->enrollment->certification->name }}
                        @else
                            全般
                        @endif
                    </p>

                    <p class="mt-3 text-xs text-ink-400">
                        {{ $conversation->last_message_at?->format('Y/m/d H:i') ?? 'メッセージなし' }}
                    </p>
                </a>
            @endforeach
        </div>
    @endif

    @include('ai-chat._partials.new-conversation-modal')
@endsection
