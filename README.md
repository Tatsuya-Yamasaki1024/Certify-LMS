# Certify LMS

マルチ資格対応の資格学習プラットフォームです。受講生は資格ごとの教材で学習し、演習問題・模擬試験で理解度を確かめながら、コーチの面談サポートを受けて資格取得を目指せます。

> プロジェクト構造・ドメインモデル・コードの読み進め方は [ONBOARDING.md](./ONBOARDING.md) を参照してください。

## 主な機能

| ロール            | 機能                                                                                                                                                        |
| ----------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 受講生（student） | 教材閲覧 / 演習問題・苦手分野ドリル / 模擬試験（分野別ヒートマップ・合格可能性スコア）/ 面談予約 / チャット / 学習時間・進捗・ストリーク管理 / 修了証の受領 |
| コーチ（coach）   | 教材・演習問題・模試の管理 / 担当受講生の進捗フォロー / 面談対応・面談メモ / チャット                                                                       |
| 管理者（admin）   | ユーザー招待・管理 / 資格・資格分類マスタ管理 / 資格へのコーチ割当 / 面談回数の付与 / 全体ダッシュボード                                                    |

## 動作環境

- Docker Desktop / Docker Compose
- 開発環境は Laravel Sail で構築します（PHP コンテナ・MySQL・Mailpit・phpMyAdmin を起動）

## 環境構築手順

### 1. リポジトリの clone

```bash
git clone <このリポジトリの URL>
cd <リポジトリ名>
```

### 2. 環境変数ファイルの作成

```bash
cp .env.example .env
```

`.env.example` は Sail 向けに設定済みのため、コピーするだけでローカル開発を始められます（外部サービス連携のキーは後述）。

### 3. 依存パッケージのインストール（初回のみ）

`vendor/` がまだ無いため、初回のみ Docker 経由で Composer を実行します。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

### 4. Sail エイリアスの設定（推奨）

```bash
alias sail='./vendor/bin/sail'
```

以降のコマンドはこのエイリアス前提で記載します（未設定の場合は `./vendor/bin/sail` に読み替えてください）。

### 5. コンテナの起動

```bash
sail up -d
```

### 6. アプリケーションの初期化

```bash
sail artisan key:generate
sail artisan storage:link
sail artisan migrate:fresh --seed
```

`storage:link` は教材画像・プロフィール画像の配信に必要です。`migrate:fresh --seed` でテーブル作成とデモデータ投入が行われます（いつでも再実行してデータを初期状態に戻せます）。

### 7. フロントエンドのビルド

```bash
sail npm install
sail npm run build
```

Blade / CSS / JS を編集しながら開発する場合は、`build` の代わりに `sail npm run dev` を起動したままにしてください（Vite のホットリロードが効きます）。

### 8. 動作確認

http://localhost:8000 にアクセスし、下記の[ログインアカウント](#ログインアカウント)でログインできればセットアップ完了です。

## 開発環境 URL

| 用途                  | URL                   |
| --------------------- | --------------------- |
| アプリケーション      | http://localhost:8000 |
| phpMyAdmin（DB 確認） | http://localhost:8080 |
| Mailpit（メール確認） | http://localhost:8025 |

アプリケーションが送信するメール（招待メールなど）はすべて Mailpit に届きます。実際のメールは送信されません。

## ログインアカウント

`migrate:fresh --seed` 後、以下の固定アカウントが使えます（パスワードはすべて `password`）。

| ロール | メールアドレス           | 備考                                             |
| ------ | ------------------------ | ------------------------------------------------ |
| 管理者 | admin@certify-lms.test   | 全機能にアクセス可能                             |
| コーチ | coach@certify-lms.test   | IT 系資格の担当                                  |
| コーチ | coach2@certify-lms.test  | ビジネス系資格の担当                             |
| 受講生 | student@certify-lms.test | 受講中の資格・学習履歴・面談などのデモデータ付き |

このほか、ライフサイクル（招待中 / 受講中 / 卒業 / 退会）を網羅したデモユーザーが投入されます。

> 本サービスは**招待制**です。公開の会員登録画面はありません。新規ユーザーを作るには、管理者でログイン → ユーザー管理から招待 → Mailpit で招待メールの URL を開く → オンボーディング登録、という流れになります。

## テスト

```bash
sail artisan test                  # 全テスト実行
sail artisan test --filter=Xxx    # クラス名・メソッド名で絞り込み
```

## コード整形

Laravel Pint を使用しています。コミット前に実行してください。

```bash
sail bin pint --dirty    # 変更ファイルのみ整形
sail bin pint --test     # 整形漏れの確認（CI 相当のチェック）
```

## 使用技術

- PHP 8.5 / Laravel 10
- MySQL 8.4
- Laravel Fortify（認証）/ Laravel Sanctum（API 認証）
- Blade + Tailwind CSS + Vite（JavaScript は素の JS、フレームワーク不使用）
- PHPUnit / Laravel Pint
- league/commonmark（教材本文の Markdown レンダリング）
- Pusher（チャットのリアルタイム配信）
- Docker（Laravel Sail）

## 環境変数

`.env.example` をコピーすると、基本機能をローカルで利用できます（メールは Mailpit に配信されます）。

Stripeなどの外部サービス連携を利用する場合は、別途APIキーなどの設定が必要です。

### Pusher（チャットのリアルタイム配信）

- `PUSHER_*` — チャットのリアルタイム配信に使用します。有効にする場合はPusherのキーを取得して設定し、`BROADCAST_DRIVER=pusher` に変更してください。
- 未設定（既定の `BROADCAST_DRIVER=log`）でもメッセージの送受信自体は動作します。ただし、相手画面へのリアルタイム反映は行われません。

### Stripe（追加面談パック購入）

追加面談パックの購入にはStripeを使用します。

実際にテスト決済を行う場合は、Stripeのテスト環境用APIキーとStripe CLIの設定が必要です。

#### 1. 環境変数の設定

`.env` に以下を設定してください。

```dotenv
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_CURRENCY=jpy
```

各環境変数の用途は以下のとおりです。

- `STRIPE_SECRET` — Stripeのテスト環境用シークレットキー。Stripeダッシュボードから取得します。
- `STRIPE_WEBHOOK_SECRET` — Stripe Webhookの署名検証に使用するシークレット。Stripe CLIでWebhook転送を開始した際に表示されます。
- `STRIPE_CURRENCY` — 決済通貨。既定値は `jpy`（日本円）です。

APIキーやWebhookシークレットはGitにコミットしないでください。

設定を変更した場合は、必要に応じてLaravelの設定キャッシュをクリアしてください。

```bash
./vendor/bin/sail artisan config:clear
```

#### 2. Stripe CLIのセットアップ

Stripe CLIをインストールし、Stripeアカウントにログインします。

```bash
stripe login
```

Laravel Sailを起動します。

```bash
./vendor/bin/sail up -d
```

別のターミナルで、Stripe CLIによるWebhook転送を開始します。

```bash
stripe listen --forward-to localhost:8000/webhooks/stripe
```

コマンド実行後に表示される `whsec_...` を `.env` の `STRIPE_WEBHOOK_SECRET` に設定してください。

Webhookの受信先は以下です。

```text
POST /webhooks/stripe
```

このルートはStripeサーバーから呼び出されるため、Laravelのログイン認証は使用しません。

代わりに、Stripeから送信される `Stripe-Signature` ヘッダーを検証し、正当なWebhookリクエストのみ処理します。

#### 3. テスト決済の手順

1. `student@certify-lms.test`（パスワード：`password`）でログインします。
2. `/meeting-quota/checkout` にアクセスします。
3. 公開済みの面談パックを選択して購入手続きを開始します。
4. Stripe Checkoutの決済画面で、以下のテストカード情報を入力します。
    - カード番号：`4242 4242 4242 4242`
    - 有効期限：将来の年月
    - CVC：任意の3桁
5. 決済を完了します。
6. Stripe CLIに `checkout.session.completed` イベントが届くことを確認します。
7. 購入履歴と面談残数が更新されていることを確認します。

決済開始時に購入履歴が `pending` 状態で作成されます。

決済成功後、Stripe Webhookを受信すると購入履歴が `succeeded` に更新され、購入した面談パックの回数が面談残数に加算されます。

面談回数は購入時点の値を保存するため、購入後に面談パックの設定が変更されても、付与される回数は変わりません。

また、同じWebhookイベントが複数回送信されても、同一決済による面談回数の二重付与は行われません。

#### 4. 開発用初期データ

`migrate:fresh --seed` を実行すると、`PaymentSeeder` により開発用の購入履歴が作成されます。

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

以下の状態を確認できるデータが投入されます。

| 決済状態            |     件数 | 面談回数の付与      |
| ------------------- | -------: | ------------------- |
| `succeeded`（成功） |      4件 | あり                |
| `pending`（保留）   |      4件 | なし                |
| `failed`（失敗）    |      4件 | なし                |
| **合計**            | **12件** | **成功した4件のみ** |

固定受講生とデモ受講生3名に購入履歴が作成されます。

なお、面談残数0の動作確認用アカウント `student-noquota@certify-lms.test` には購入履歴を作成せず、面談残数0の状態を維持します。

`PaymentSeeder` は同じデータに対して再実行しても、購入履歴と面談回数の付与履歴が重複しないように実装しています。

#### 5. 自動テスト

Stripeの実APIに接続せず、モックと署名付きWebhookリクエストを使用して動作を検証できます。

関連テストは以下のコマンドで実行します。

```bash
./vendor/bin/sail artisan test \
  tests/Feature/Http/MeetingQuota/CheckoutTest.php \
  tests/Feature/Http/MeetingQuota/StripeWebhookTest.php \
  tests/Feature/Http/MeetingQuota/PaymentMeetingCountSnapshotTest.php \
  tests/Feature/Http/MeetingPack/DestroyTest.php
```

主に以下の動作を検証します。

- 公開済み面談パックのみ購入できること
- 決済開始時に購入履歴が作成されること
- 決済成功時に面談回数が付与されること
- Webhookの重複受信で二重付与されないこと
- 決済失敗・期限切れ時に適切な状態へ更新されること
- 不正な署名のWebhookが拒否されること
- 購入時点の面談回数が保存されること
- 購入後に面談パックの回数を変更しても、購入時点の回数が付与されること
- 購入履歴が存在する面談パックを削除できないこと

---

新しい環境変数やセットアップ手順を追加した場合は、`.env.example` と本READMEに追記し、チームの誰でも環境を再現できる状態を保ってください。
