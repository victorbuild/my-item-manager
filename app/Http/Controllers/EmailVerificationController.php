<?php

namespace App\Http\Controllers;

use App\Services\EmailVerificationService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * 驗證登入者與簽名連結中的帳號及信箱，重複開啟不重複觸發驗證事件。
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect('/verify-email?verified=1');
    }

    /**
     * 只寄送目前登入帳號的驗證信，不接受外部指定收件人。
     */
    public function send(Request $request, EmailVerificationService $verification): JsonResponse
    {
        $sent = $verification->send($request->user());

        return response()->json([
            'success' => $sent,
            'message' => $sent ? '驗證信已寄出，請查看收件匣與垃圾郵件。' : '目前無法寄信，請稍後重新寄送。',
            'data' => null,
        ], $sent ? 200 : 503);
    }
}
