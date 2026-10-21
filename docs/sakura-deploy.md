# さくらのレンタルサーバへの設置手順（案）

> この手順は、さくらのレンタルサーバ（スタンダード以上・SSH利用可）を想定した案です。実際の契約プラン・コントロールパネルの表示で確認しながら進めてください。手順中の「要確認」は、まだ実機で確かめていない点です。

## 前提

- PHP 8.3 以上（コントロールパネルの「言語のバージョン設定」で選択）。参考：[PHPのバージョンを変更したい](https://help.sakura.ad.jp/rs/2241/)
- MySQL 8.0（コントロールパネルでデータベースを作成）
- SSH でログインできること
- https で公開できること（無料SSLなど）

## 1. 配置

1. SSH でログインし、ホームディレクトリにアプリを置きます（例：`~/partner-app`）。公開フォルダ（`www`）の中には置きません。`.env` などが外から見えないようにするためです。
2. サブドメイン（例：`partner.example.co.jp`）を追加し、その公開フォルダを `partner-app/public` に設定します（要確認：コントロールパネルでホーム直下の任意フォルダを指定できるか）。
3. Composer を用意し（`curl -sS https://getcomposer.org/installer | php`）、`php composer.phar install --no-dev --optimize-autoloader` を実行します。SSH の `php` コマンドのバージョンがWeb側と違う場合は、8.3 以上の PHP を明示して実行します（要確認：CLI 用 PHP のパス）。

## 2. 設定（.env）

`.env.example` をコピーして `.env` を作り、次を本番用にします。

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://partner.example.co.jp
SESSION_SECURE_COOKIE=true
PARTNER_DEV_TOOLS=false
DB_HOST=（さくらのDBサーバー名）
DB_DATABASE=… DB_USERNAME=… DB_PASSWORD=…
MAIL_MAILER=smtp
MAIL_HOST=（送信サーバー）  MAIL_PORT=587  MAIL_USERNAME=…  MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=（送信元アドレス）
PARTNER_INTAKE_API_KEY=（方法Bで使う場合のみ。32文字以上のランダム文字列）
```

`php artisan key:generate` で APP_KEY を作ります。本番モードで Mailpit・テスト予約画面・デバッグ表示などの開発用設定が残っていると、アプリは起動しません（安全のため）。

## 3. 初期化

```
php artisan migrate --force
php artisan partner:create-admin you@example.co.jp "管理者名"
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`storage/` と `bootstrap/cache/` は書き込み可能にします。

## 4. メール送信（CRON）

コントロールパネルの CRON 設定で、毎分次を実行します（要確認：最短の実行間隔。毎分が使えない場合は、使える最短間隔で `partner:send-mail` を直接実行します）。

```
cd ~/partner-app && php artisan schedule:run >> /dev/null 2>&1
```

## 5. 公開前の確認

管理画面のホームに「本番公開前に設定が必要な項目」が表示されます。すべて埋めてから公開してください（総額上限、予約先URL、通知先、プライバシーポリシー、保存期間、見積条件の未設定項目）。

## 更新時

```
git pull   # または新しいファイルをアップロード
php composer.phar install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

PHP 本体はさくら側で更新されます。コントロールパネルで、サポートされている最新の 8.3 以上を選んでください。依存パッケージの脆弱性は `composer audit` で確認できます。
