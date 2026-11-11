<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyContactEmail;
use App\Support\Logo;
use Epesi\Modules\Appearance\Support\MailLogoPlate;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shared epesi mail theme (resources/views/vendor/mail): wording, footer,
 * the logo picture and the password-reset e-mail's "48 hours".
 */
class MailThemeTest extends TestCase
{
    // The logo comes from the Appearance settings table.
    use RefreshDatabase;

    private function html(object $notification): string
    {
        return (string) $notification->toMail(new User)->render();
    }

    public function test_the_reset_mail_says_hours_not_minutes_and_uses_the_epesi_wording(): void
    {
        $this->assertSame(2880, (int) config('auth.passwords.users.expire'));

        $notification = new ResetPasswordNotification('token');
        $notification->url = 'https://crm.example.test/reset?token=abc';

        $html = $this->html($notification);

        $this->assertStringContainsString('Dear Epesi User,', $html);
        $this->assertStringContainsString('expire in 48 hours', $html);
        $this->assertStringNotContainsString('2880', $html);
        $this->assertStringContainsString('Your Epesi team', $html);
        $this->assertStringContainsString('https://crm.example.test/reset?token=abc', $html);
        $this->assertStringContainsString('https://epesicrm.com', $html);
        $this->assertStringContainsString('2006-'.date('Y'), $html);
    }

    public function test_a_life_that_is_not_whole_hours_is_said_in_minutes(): void
    {
        config(['auth.passwords.users.expire' => 90]);

        $notification = new ResetPasswordNotification('token');
        $notification->url = 'https://crm.example.test/reset';

        $this->assertStringContainsString('expire in 90 minutes', $this->html($notification));
    }

    public function test_every_panel_sends_the_epesi_reset_notification(): void
    {
        $this->assertInstanceOf(ResetPasswordNotification::class, app(FilamentResetPassword::class, ['token' => 't']));
    }

    public function test_the_other_mails_share_the_theme(): void
    {
        $html = $this->html(new VerifyContactEmail('https://crm.example.test/verify'));

        $this->assertStringContainsString('Dear Epesi User,', $html);
        $this->assertStringContainsString('class="logo"', $html);
        $this->assertStringContainsString('https://crm.example.test/verify', $html);
    }

    public function test_the_mail_logo_is_an_absolute_url_of_a_picture_with_its_plate(): void
    {
        $logo = Logo::mail();

        $this->assertStringStartsWith('http', $logo['url']);
        $this->assertMatchesRegularExpression('#logo-mail\.png|branding/\w+/mail#', $logo['url']);
        $this->assertNotSame('', $logo['alt']);
    }

    public function test_the_plate_is_a_full_width_png(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is not available.');
        }

        $source = imagecreatetruecolor(300, 60);
        ob_start();
        imagepng($source);
        $png = MailLogoPlate::render((string) ob_get_clean());

        $this->assertNotNull($png);
        [$width, $height] = getimagesizefromstring($png);
        $this->assertSame(MailLogoPlate::WIDTH * 2, $width);
        $this->assertSame(MailLogoPlate::HEIGHT * 2, $height);
        $this->assertNull(MailLogoPlate::render('not a picture'));
    }
}
