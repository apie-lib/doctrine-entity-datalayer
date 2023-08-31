<?php
namespace Apie\DoctrineEntityDatalayer;

final class LikeUtils
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    public static function toLikeString(string $input): string
    {
        return '%' . str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $input) . '%';
    }
}
