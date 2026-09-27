<?php

namespace Tests\Feature;

use App\Support\Mail\ServerMailTransport;
use Illuminate\Support\Facades\Mail;
use ReflectionProperty;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Tests\TestCase;

/**
 * "This server's mail system", Epesi's "local php.ini settings": php.ini's
 * sendmail_path, or on Windows its SMTP server and port — where XAMPP sends
 * mail to a catcher such as Papercut on localhost:25. Laravel's own sendmail
 * mailer ran /usr/sbin/sendmail, which a Windows PC doesn't have.
 */
class ServerMailTransportTest extends TestCase
{
    public function test_php_inis_sendmail_command_is_used(): void
    {
        $transport = $this->transport(['sendmail_path' => '/usr/local/bin/msmtp -t -i'], windows: false);

        $this->assertSame('/usr/local/bin/msmtp -t -i', $this->command($transport));
    }

    public function test_on_windows_php_inis_smtp_server_and_port_are_used(): void
    {
        $transport = $this->transport(['sendmail_path' => '', 'SMTP' => 'mail.example.test', 'smtp_port' => '2525'], windows: true);

        $this->assertSame('smtp://mail.example.test:2525', (string) $transport);
    }

    public function test_xampps_default_reaches_localhost_25(): void
    {
        $transport = $this->transport(['sendmail_path' => '', 'SMTP' => 'localhost', 'smtp_port' => '25'], windows: true);

        $this->assertSame('smtp://localhost', (string) $transport);
    }

    public function test_the_smtp_setting_is_ignored_off_windows(): void
    {
        // PHP itself only uses SMTP/smtp_port on Windows.
        $transport = $this->transport(['sendmail_path' => '', 'SMTP' => 'mail.example.test', 'smtp_port' => '2525'], windows: false);

        $this->assertSame(ServerMailTransport::FALLBACK_COMMAND, $this->command($transport));
    }

    public function test_a_command_of_your_own_wins(): void
    {
        $transport = $this->transport(
            ['sendmail_path' => '/usr/sbin/sendmail -t -i', 'SMTP' => 'localhost', 'smtp_port' => '25'],
            windows: true,
            config: ['path' => '/opt/mail/send -bs'],
        );

        $this->assertSame('/opt/mail/send -bs', $this->command($transport));
    }

    public function test_a_command_symfony_cannot_talk_to_falls_through(): void
    {
        // No -bs and no -t: PHP would run it, Symfony refuses it.
        $transport = $this->transport(['sendmail_path' => '/usr/sbin/sendmail', 'SMTP' => 'localhost', 'smtp_port' => '25'], windows: true);

        $this->assertSame('smtp://localhost', (string) $transport);
    }

    public function test_the_sendmail_mailer_uses_it(): void
    {
        $this->assertSame('native', config('mail.mailers.sendmail.transport'));
        $this->assertInstanceOf(TransportInterface::class, Mail::mailer('sendmail')->getSymfonyTransport());
    }

    /** The command a sendmail transport runs (Symfony keeps it private). */
    protected function command(TransportInterface $transport): string
    {
        $this->assertInstanceOf(SendmailTransport::class, $transport);

        return (new ReflectionProperty(SendmailTransport::class, 'command'))->getValue($transport);
    }

    /**
     * @param  array<string, string>  $ini
     * @param  array<string, mixed>  $config
     */
    protected function transport(array $ini, bool $windows, array $config = []): TransportInterface
    {
        return ServerMailTransport::make($config, fn (string $key): string|false => $ini[$key] ?? false, $windows);
    }
}
