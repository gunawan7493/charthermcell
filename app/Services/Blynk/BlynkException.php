<?php

namespace App\Services\Blynk;

use RuntimeException;

class BlynkException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('Token Blynk belum diatur di server.');
    }

    public static function unreachable(string $reason): self
    {
        return new self('Tidak dapat menghubungi Blynk: '.$reason);
    }

    public static function rejected(string $message): self
    {
        return new self($message);
    }
}
