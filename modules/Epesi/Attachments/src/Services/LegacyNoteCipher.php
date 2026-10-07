<?php

namespace Epesi\Modules\Attachments\Services;

/** Decoder for Epesi Utils/Attachment's historical password-protected note format. */
class LegacyNoteCipher
{
    /** Encode a note in the password format used by Epesi Utils/Attachment. */
    public function encrypt(string $note, string $password, string $hint = ''): string
    {
        if (! $this->loadCompatibilityLayer()) {
            throw new \RuntimeException('The legacy note encryption compatibility layer is unavailable.');
        }

        $td = mcrypt_module_open('rijndael-256', '', 'cbc', '');
        $iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($td));
        $key = substr(sha1($password), 0, mcrypt_enc_get_key_size($td));
        mcrypt_generic_init($td, $key, $iv);
        $ciphertext = mcrypt_generic($td, $note.md5($note));
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);

        return base64_encode($ciphertext)."\n".base64_encode($iv)."\n".$hint;
    }

    /**
     * Legacy encrypted payloads are base64(ciphertext), base64(IV), and an
     * optional plaintext hint separated by newlines. The compatibility shim
     * matches the legacy mcrypt Rijndael-256/CBC behavior exactly.
     */
    public function decrypt(string $payload, string $password): ?string
    {
        if (! $this->loadCompatibilityLayer()) {
            return null;
        }

        $parts = explode("\n", $payload, 3);
        if (count($parts) < 2) {
            return null;
        }

        $ciphertext = base64_decode($parts[0], true);
        $iv = base64_decode($parts[1], true);

        if ($ciphertext === false || $iv === false || $ciphertext === '' || strlen($iv) !== 32 || strlen($ciphertext) % 32 !== 0) {
            return null;
        }

        $td = mcrypt_module_open('rijndael-256', '', 'cbc', '');
        $key = substr(sha1($password), 0, mcrypt_enc_get_key_size($td));
        mcrypt_generic_init($td, $key, $iv);
        $plain = mdecrypt_generic($td, $ciphertext);
        mcrypt_generic_deinit($td);
        mcrypt_module_close($td);

        $plain = rtrim($plain, "\0");
        if (strlen($plain) < 32) {
            return null;
        }

        $digest = substr($plain, -32);
        $note = substr($plain, 0, -32);

        return hash_equals(md5($note), $digest) ? $note : null;
    }

    public function hint(string $payload): ?string
    {
        $parts = explode("\n", $payload, 3);

        return isset($parts[2]) && $parts[2] !== '' ? $parts[2] : null;
    }

    private function loadCompatibilityLayer(): bool
    {
        if (function_exists('mcrypt_module_open')) {
            return true;
        }

        $polyfill = base_path('vendor/phpseclib/mcrypt_compat/lib/mcrypt.php');
        if (! is_file($polyfill)) {
            return false;
        }

        require_once $polyfill;

        return function_exists('mcrypt_module_open');
    }
}
