<?php

use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Mail;

test('resend can be selected as the default mailer with an api key', function () {
    config([
        'mail.default' => 'resend',
        'services.resend.key' => 're_test_key',
    ]);

    $transport = Mail::mailer()->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(ResendTransport::class);
});
