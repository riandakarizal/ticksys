<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Signature('app:test-mail {email : Recipient email address} {--subject=TicketSys SMTP Test : Email subject}')]
#[Description('Send a test email using the current mail configuration')]
class TestMail extends Command
{
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $subject = (string) $this->option('subject');

        try {
            Mail::raw(
                "This is a TicketSys SMTP test email.\n\nIf you received this, your mail configuration is working.",
                function ($mail) use ($email, $subject): void {
                    $mail->to($email)->subject($subject);
                }
            );
        } catch (Throwable $exception) {
            $this->error('Failed to send test email.');
            $this->line($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Test email sent successfully.');

        return self::SUCCESS;
    }
}
