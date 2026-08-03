<?php

namespace Leaf\Helpers;

/**
 * Minimal stand-in for leafs/password used by the @hash seed token.
 */
if (!class_exists(Password::class)) {
    class Password
    {
        public static function hash(string $value): string
        {
            return password_hash($value, PASSWORD_DEFAULT);
        }
    }
}
