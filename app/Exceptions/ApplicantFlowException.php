<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 応募者向けの手続きを止める事象。kind ごとに画面を出し分ける。
 * 設定不備や保存失敗を「不採用」と表示しないための区別。
 */
class ApplicantFlowException extends RuntimeException
{
    public const MESSAGES = [
        'invalid' => ['title' => 'このURLは無効です', 'body' => 'URLが正しくないか、すでに使えなくなっています。お手数ですが、担当者へお問い合わせください。', 'status' => 404],
        'expired' => ['title' => 'URLの有効期限が切れています', 'body' => 'お手数ですが、担当者へお問い合わせください。新しいURLをお送りします。', 'status' => 410],
        'session' => ['title' => 'ページを表示できません', 'body' => 'メールでお送りした専用URLから、もう一度アクセスしてください。', 'status' => 403],
        'save_failed' => ['title' => '保存できませんでした', 'body' => '通信またはシステムの問題で、内容を保存できませんでした。提出は完了していません。時間をおいて再度お試しいただくか、担当者へお問い合わせください。', 'status' => 503],
        'config_incomplete' => ['title' => '現在手続きを進められません', 'body' => '現在、お手続きを進められない状態です。担当者へお問い合わせください。', 'status' => 503],
        'booking_unset' => ['title' => '現在手続きを進められません', 'body' => '面談日程の登録先を準備中です。担当者へお問い合わせください。', 'status' => 503],
        'already_submitted' => ['title' => '見積は提出済みです', 'body' => 'この応募の見積はすでに提出されています。', 'status' => 409],
        'slot_taken' => ['title' => 'この日時は予約できません', 'body' => '選択した日時は、ほかの方の予約が入ったか、受付を締め切りました。別の日時をお選びください。', 'status' => 409],
        'already_booked' => ['title' => '面談はすでに予約済みです', 'body' => '日程の変更をご希望の場合は、担当者へお問い合わせください。', 'status' => 409],
        'forbidden' => ['title' => 'このページは表示できません', 'body' => 'このページを表示する権限がありません。', 'status' => 403],
    ];

    public function __construct(public readonly string $kind)
    {
        parent::__construct($kind);
    }

    public function info(): array
    {
        return self::MESSAGES[$this->kind] ?? self::MESSAGES['invalid'];
    }
}
