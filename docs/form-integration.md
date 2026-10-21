# 既存の問い合わせフォームとの接続

本アプリは、次の2通りで既存サイト（フェニックスのサイト）とつなげられます。どちらにするかは、既存フォームの作りを確認して決めます。

## 方法A：リンクで応募画面へ移動する（おすすめ・最も簡単）

既存フォームで「① 一括制作パートナー」「② 8341パートナー」を選ぶ部分を、本アプリの応募画面へのリンク（またはボタン）にします。

- ① 一括制作 → `https://（本アプリのURL）/apply/partner`
- ② 8341 → `https://（本アプリのURL）/apply/8341`

応募の入力・保存・自動返信はすべて本アプリが行います。①の方は、応募後にそのまま見積入力へ進みます。

**重複する自動返信の止め方**：既存フォームでは①②の応募を受け付けないため、既存フォーム側の自動返信は変更不要です（①②以外のお問い合わせは従来どおり）。

## 方法B：既存フォームの送信内容をサーバー間で本アプリへ送る

既存フォームで入力を受け付けたまま、その送信処理（サーバー側）から本アプリの受付APIを呼びます。

```
POST https://（本アプリのURL）/api/intake/applications
Authorization: Bearer （.env の PARTNER_INTAKE_API_KEY）
Content-Type: application/json
```

送る項目は応募フォームと同じです（`name`, `kana`, `email`, `phone`, `prefecture`, `experience_years`, `experience_months`, `portfolio_url`, `role_scope`, `monthly_hours`, `available_from`, `can_full_scope`, `can_online_meeting`, `self_intro`, `privacy`）。加えて次の2つが必須です。

- `category`：`p1`（一括制作）または `p2`（8341）
- `event_id`：既存フォーム側の送信ごとに一意な値。同じ値の再送は二重登録されず、最初の結果を返します。

応答例（①・応募後そのまま見積へ進む設定のとき）：

```json
{ "number": "P1-2026-00001", "category": "p1", "replayed": false, "next_url": "https://.../invite/xxxx" }
```

`next_url` は応募者本人用のURLです。既存フォームの完了後に、このURLへ応募者を移動させると、メールを待たずに見積入力へ進めます。ログやブラウザの画面に表示しないでください。

### PHP（既存フォームの送信処理）からの呼び出し例

```php
$payload = [
    'event_id' => $submissionId,          // 既存フォームの送信ID
    'category' => $_POST['kind'] === 'partner' ? 'p1' : 'p2',
    'name' => $_POST['name'],
    // ……応募項目……
    'privacy' => '1',
];
$ch = curl_init('https://partner.example.co.jp/api/intake/applications');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer '.getenv('PARTNER_INTAKE_API_KEY')],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$body = json_decode(curl_exec($ch), true);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// 201 = 新規受付、200 = 再送（同じ event_id）、422 = 入力エラー、401 = キー誤り
```

- APIキーはサーバー側の環境変数に置き、HTML・JavaScriptへ出さないでください。
- **重複する自動返信の止め方**：方法Bでは本アプリが①②の自動返信を送ります。既存フォーム側で①②を選んだ場合の自動返信（同じ内容のもの）は停止してください。①②以外の問い合わせの自動返信は従来どおりで構いません。
