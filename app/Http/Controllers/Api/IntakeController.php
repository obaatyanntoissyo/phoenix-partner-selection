<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AppSetting;
use App\Services\ApplicationService;
use App\Support\ApplicationRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 既存の問い合わせフォームからのサーバー間受付API。
 * Authorization: Bearer <PARTNER_INTAKE_API_KEY>。event_id で再送を重複処理しない。
 */
class IntakeController extends Controller
{
    public function __construct(private ApplicationService $service) {}

    public function store(Request $request): JsonResponse
    {
        $key = (string) config('partner.intake_api_key');
        $given = (string) $request->bearerToken();
        if (strlen($key) < 32 || ! hash_equals($key, $given)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $meta = Validator::make($request->all(), [
            'event_id' => ['required', 'string', 'max:191'],
            'category' => ['required', 'in:p1,p2'],
        ])->validate();

        $input = ApplicationRules::normalize($request->except(['event_id', 'category']));
        $validated = Validator::make($input, ApplicationRules::rules($meta['category']), ApplicationRules::messages(), ApplicationRules::attributes())->validate();

        $result = $this->service->register(
            $meta['category'],
            ApplicationRules::toAttributes($validated, AppSetting::get('privacy_policy_version', '未設定')),
            'api',
            $meta['event_id'],
        );

        $body = [
            'number' => $result['application']->number,
            'category' => $result['application']->category,
            'replayed' => $result['replayed'],
        ];
        // フォーム送信後に応募者をそのまま見積へ移動させる場合だけ、本人用URLを返す（サーバー間のみ）
        if ($result['token'] && $meta['category'] === Application::P1 && AppSetting::get('flow_mode', 'direct') === 'direct') {
            $body['next_url'] = route('invite.show', ['token' => $result['token']]);
        }

        return response()->json($body, $result['replayed'] ? 200 : 201);
    }
}
