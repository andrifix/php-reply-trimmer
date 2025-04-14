<?php

declare(strict_types=1);

namespace EmailReplyTrimmer\Matcher;

/**
 * Matches lines starting with a quote character '>'.
 */
class QuoteMatcher
{
    private const QUOTE_REGEX = '/^[[:blank:]]*>/u';

    public static function match(string $line): bool
    {
        return (bool) preg_match(self::QUOTE_REGEX, $line);
    }
}
