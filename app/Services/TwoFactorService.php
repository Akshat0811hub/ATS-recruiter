<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class TwoFactorService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 16): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, 31)];
        }

        return $secret;
    }

    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(5).'-'.Str::random(5));
        }

        return $codes;
    }

    public function getCurrentTotpCode(string $secret, ?int $timeSlice = null): string
    {
        $timeSlice = $timeSlice ?? (int) floor(time() / 30);
        $secretKey = $this->base32Decode($secret);
        $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord(substr($hmac, -1)) & 0x0F;
        $hashPart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashPart)[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function verifyCode(User $user, string $inputCode): bool
    {
        $inputCode = trim($inputCode);
        if (! $user->two_factor_secret) {
            return false;
        }

        $currentSlice = (int) floor(time() / 30);
        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals($this->getCurrentTotpCode($user->two_factor_secret, $currentSlice + $offset), $inputCode)) {
                return true;
            }
        }

        // Check recovery codes
        $recoveryCodes = json_decode((string) $user->two_factor_recovery_codes, true) ?: [];
        foreach ($recoveryCodes as $idx => $recoveryCode) {
            if (hash_equals(strtoupper($recoveryCode), strtoupper($inputCode))) {
                unset($recoveryCodes[$idx]);
                $user->update(['two_factor_recovery_codes' => json_encode(array_values($recoveryCodes))]);

                return true;
            }
        }

        return false;
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper($secret);
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
            $val = strpos(self::BASE32_CHARS, $secret[$i]);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }
}
