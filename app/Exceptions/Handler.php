<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use GuzzleHttp\Client;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

//    public function report(Throwable $exception)
//    {
//        // 你可以根据具体的异常类型或严重程度来决定是否发送通知
//        if ($this->shouldReport($exception)) {
//            $this->sendErrorNotification($exception);
//        }
//
//        parent::report($exception);
//    }

    public function report(Throwable $exception)
    {
        // 强制报告所有异常
        parent::report($exception);

        // 发送通知到 LINE（之前你配置的代码）
        $this->sendErrorNotification($exception);
    }


    protected function sendErrorNotification(Throwable $exception)
    {
        try {

            $owen_token = config('app.line_owen_token');
            $client   = new Client();
            $headers  = [
                'Authorization' => sprintf('Bearer %s', $owen_token),
                'Content-Type'  => 'application/x-www-form-urlencoded'
            ];
            $options  = [
                'form_params' => [
                    'message' => 'Error: ' . $exception->getMessage() . "\n" .
                        'File: ' . $exception->getFile() . "\n" .
                        'Line: ' . $exception->getLine()
                ]
            ];
            $response = $client->request('POST', 'https://notify-api.line.me/api/notify', [
                'headers'     => $headers,
                'form_params' => $options['form_params']
            ]);
        } catch (Throwable $e) {
            // 这里你可以记录日志，防止错误通知本身出现问题
            parent::report($e);
        }
    }

}
