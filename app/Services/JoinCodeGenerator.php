<?php

namespace App\Services;

use App\Models\Organisation;

class JoinCodeGenerator
{
    public static function generate(int $length = 8): string
    {
        // No confusing characters: no O/0, I/1
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Organisation::where('join_code', $code)->exists());

        return $code;
    }
}
