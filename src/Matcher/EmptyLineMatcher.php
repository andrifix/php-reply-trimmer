<?php

declare(strict_types=1);

namespace EmailReplyTrimmer\Matcher;

/**
 * Matches empty or whitespace-only lines.
 */
class EmptyLineMatcher
{
    private const EMPTY_LINE_REGEX = '/^[[:blank:]]*$/u';

    public static function match(string $line): bool
    {
        return (bool) preg_match(self::EMPTY_LINE_REGEX, $line);
    }
}
