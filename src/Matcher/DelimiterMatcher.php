<?php

declare(strict_types=1);

namespace EmailReplyTrimmer\Matcher;

/**
 * Matches delimiter lines (e.g., lines of dashes).
 */
class DelimiterMatcher
{
    private const DELIMITER_CHARACTERS = "-_,=+~#*ᐧ—";
    private const DELIMITER_REGEX = '/^[[:blank:]]*[' . self::DELIMITER_CHARACTERS . ']+[[:blank:]]*$/u';

    public static function match(string $line): bool
    {
        $regex = '/^[[:blank:]]*[' . preg_quote(self::DELIMITER_CHARACTERS, '/') . ']+[[:blank:]]*$/u';
        return (bool) preg_match($regex, $line);
    }
}
