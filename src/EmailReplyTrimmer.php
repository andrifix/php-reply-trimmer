<?php

declare(strict_types=1);

namespace EmailReplyTrimmer;

use EmailReplyTrimmer\Matcher\DelimiterMatcher;
use EmailReplyTrimmer\Matcher\EmailHeaderMatcher;
use EmailReplyTrimmer\Matcher\EmbeddedEmailMatcher;
use EmailReplyTrimmer\Matcher\EmptyLineMatcher;
use EmailReplyTrimmer\Matcher\QuoteMatcher;
use EmailReplyTrimmer\Matcher\SignatureMatcher;
use Exception;

/**
 * Trims replies, signatures, and forwarded messages from email bodies.
 *
 * Ported from the Ruby gem email_reply_trimmer.
 */
class EmailReplyTrimmer
{
    public const VERSION = "0.2.0";

    public const DELIMITER    = "d";
    public const EMBEDDED     = "b";
    public const EMPTY        = "e";
    public const EMAIL_HEADER = "h";
    public const QUOTE        = "q";
    public const SIGNATURE    = "s";
    public const TEXT         = "t";

    /**
     * Identifies the type of content in a line
     *
     * @param string $line
     * @return string One of the line constants
     */
    protected static function identifyLineContent(string $line): string
    {
        if (EmptyLineMatcher::match($line)) return self::EMPTY;
        if (DelimiterMatcher::match($line)) return self::DELIMITER;
        if (SignatureMatcher::match($line)) return self::SIGNATURE;
        if (EmbeddedEmailMatcher::match($line)) return self::EMBEDDED;
        if (EmailHeaderMatcher::match($line)) return self::EMAIL_HEADER;
        if (QuoteMatcher::match($line)) return self::QUOTE;
        return self::TEXT;
    }

    /**
     * Trims the quoted replies and signatures from email text.
     *
     * @param string|null $text The email body text.
     * @param bool $split If true, returns an array: [trimmed_text, elided_text]. Otherwise, returns only trimmed_text.
     * @return string|array|null The trimmed text or array [trimmed, elided] or null if input was null/empty.
     */
    public static function trim(?string $text, bool $split = false) : string|array|null
    {
        if ($text === null || preg_match('/\A\s*\z/mu', $text)) {
            // Return type depends on $split flag
            return $split ? [null, null] : null;
        }

        // Do some cleanup
        $text = self::preprocess($text);

        // Stash the code blocks - replace them with hashes
        list($text, $blocks) = self::hoistCodeBlocks($text);

        // Work line by line
        $lines = explode("\n", $text);
        $lines_dup = $lines; // Keep original lines for elided calculation if needed

        // Create a string of characters, one per line, according to the line content
        $pattern = implode('', array_map([self::class, 'identifyLineContent'], $lines));

        // Remove everything after the first delimiter
        $delimiterPos = strpos($pattern, self::DELIMITER);
        if ($delimiterPos !== false) {
            $pattern = substr($pattern, 0, $delimiterPos);
            $lines = array_slice($lines, 0, $delimiterPos);
        }

        // Remove all mobile signatures
        while (($sigPos = strpos($pattern, self::SIGNATURE)) !== false) {
            $pattern = substr_replace($pattern, '', $sigPos, 1);
            array_splice($lines, $sigPos, 1); // Remove element from lines array
        }

        // When the reply is at the end of the email (embedded marker, non-text, then text)
        if (self::isReplyAtEnd($pattern)) {
            if (preg_match('/t[et]*$/u', $pattern, $matches, PREG_OFFSET_CAPTURE)) {
                $index = intval($matches[0][1]);
                $pattern = "";
                $lines = array_slice($lines, $index);
            }
        }

        // If there is an embedded email marker, not followed by a quote take up to marker
        if (preg_match('/(te*b[^q]*)$/u', $pattern, $matches, PREG_OFFSET_CAPTURE)) {
            $index = intval($matches[0][1]);
            $pattern = substr($pattern, 0, $index + 1);
            $lines = array_slice($lines, 0, $index + 1);
        }

        // If there is an embedded email marker, followed by a "small" quote... take up to marker
        // The original regex seems complex: /te*b[eqbh]*([te]*)$/ && $1.count("t") < 7
        if (preg_match('/te*b[eqbh]*([te]*)$/u', $pattern, $matches_with_capture)) {
            $trailingTextGroup = $matches_with_capture[1];
            $textCount = substr_count($trailingTextGroup, self::TEXT);

            if ($textCount < 7) {
                if (preg_match('/te*b[eqbh]*[te]*$/u', $pattern, $matches_for_index, PREG_OFFSET_CAPTURE)) {
                    $index = intval($matches_for_index[0][1]);

                    $pattern = substr($pattern, 0, $index + 1);
                    $lines = array_slice($lines, 0, $index + 1);
                }
            }
        }


        // If there is some text before a huge quote ending the email, remove the quote
        $matchResult = preg_match('/(t?e*[qbe]+)$/u', $pattern, $matches, PREG_OFFSET_CAPTURE);
        if ($matchResult) {
            $index = intval($matches[0][1]);
            $pattern = substr($pattern, 0, $index + 1);
            $lines = array_slice($lines, 0, $index + 1);
        }

        // If there still are some embedded email markers, just remove them
        while (($embedPos = strpos($pattern, self::EMBEDDED)) !== false) {
            $pattern = substr_replace($pattern, '', $embedPos, 1);
            array_splice($lines, $embedPos, 1);
        }

        // Fix email headers when they span over multiple lines
        $pattern = preg_replace_callback('/(h+[hte]+h+e)/u', function($match) {
            return str_repeat(self::EMAIL_HEADER, strlen($match[1]));
        }, $pattern);


        // If there are at least 3 consecutive email headers, take up to them
        if (preg_match('/t[eq]*h{3,}/u', $pattern, $matches, PREG_OFFSET_CAPTURE)) {
            $index = intval($matches[0][1]);
            $pattern = substr($pattern, 0, $index + 1);
            $lines = array_slice($lines, 0, $index + 1);
        }


        // If there still are some email headers, just remove them
        while (($headerPos = strpos($pattern, self::EMAIL_HEADER)) !== false) {
            $pattern = substr_replace($pattern, '', $headerPos, 1);
            array_splice($lines, $headerPos, 1);
        }

        // Remove trailing quotes/empty lines when there's at least one line of text left
        if (str_contains($pattern, self::TEXT)) {
            if (preg_match('/[eq]+$/u', $pattern, $matches, PREG_OFFSET_CAPTURE)) {
                $index = intval($matches[0][1]); // Start index of trailing quotes/empty
                $pattern = substr($pattern, 0, $index);
                $lines = array_slice($lines, 0, $index);
            }
        }

        $trimmed = trim(implode("\n", $lines));

        // Re-inject code blocks
        if (!empty($blocks)) {
            $trimmed = str_replace(array_keys($blocks), array_values($blocks), $trimmed);
        }

        if ($split) {
            $elided = self::computeElided($lines_dup, $lines);
            return [$trimmed, $elided];
        }

        return $trimmed;
    }

    /**
     * Extracts the first embedded email section.
     *
     * @param string|null $text The email body text.
     * @return array|null Returns an array [embedded_text, text_before_embedded] or null if no embedded section found.
     */
    public static function extractEmbeddedEmail(?string $text): ?array
    {
        if ($text === null || preg_match('/\A\s*\z/mu', $text)) {
            return null;
        }

        $text = self::preprocess($text);
        $lines = explode("\n", $text);
        $pattern = implode('', array_map([self::class, 'identifyLineContent'], $lines));

        $embedded = null;
        $startIndex = null;

        if (preg_match('/(?:h[eqd]*?){3,}[tq]/u', $pattern, $matches, PREG_OFFSET_CAPTURE)) {
            $startIndex = intval($matches[0][1]);
            $embeddedLines = array_slice($lines, $startIndex);
            $embedded = trim(implode("\n", $embeddedLines));
        }
        elseif (preg_match('/b(?:[eqd]*){3,}[tq]/u', $pattern, $matches, PREG_OFFSET_CAPTURE)) {
            $startIndex = intval($matches[0][1]);
            // Exception for quoted embedded emails (like macOS Mail)
            $embeddedLines = array_slice($lines, $startIndex + 1);
            if (!empty($embeddedLines) && QuoteMatcher::match($embeddedLines[0])) {
                $embeddedLines = array_map(fn($l) => preg_replace('/^>\s*/u', '', $l), $embeddedLines);
            }
            $embedded = trim(implode("\n", $embeddedLines));
        }

        if ($startIndex !== null) {
            $beforePattern = substr($pattern, 0, $startIndex);
            if (preg_match('/e*(b[eqd]*|b*[ed]*)$/u', $beforePattern, $matches, PREG_OFFSET_CAPTURE)) {
                $endJunkIndex = intval($matches[0][1]);
                $beforeLines = array_slice($lines, 0, $endJunkIndex);
            } else {
                $beforeLines = array_slice($lines, 0, $startIndex);
            }
            $before = trim(implode("\n", $beforeLines));

            return [$embedded, $before];
        }

        return null;
    }

    /**
     * Replaces markdown-style code blocks (```...```) with unique tokens.
     *
     * @param string $text
     * @return array [$processed_text, $blocks_dictionary].
     */
    private static function hoistCodeBlocks(string $text): array
    {
        $blocks = [];
        $regex = '/^```\w*$\n.*?\n^```$/msu';

        $processedText = preg_replace_callback($regex, function ($match) use (&$blocks) {
            try {
                $token = '---codeblock-' . bin2hex(random_bytes(8)) . '---';
                $blocks[$token] = $match[0];
                return $token;
            } catch (Exception) {
                $token = '---codeblock-' . uniqid('', true) . '---';
                $blocks[$token] = $match[0];
                return $token;
            }
        }, $text);

        if ($processedText === null) {
            return [$text, []];
        }

        return [$processedText, $blocks];
    }

    /**
     * Performs initial cleanup on the email text.
     * Normalizes line endings, removes PGP markers, unsubscribe links, fixes quote styles.
     *
     * @param string $text
     * @return string
     */
    private static function preprocess(string $text): string
    {
        // Normalize line endings
        $text = str_replace("\r\n", "\n", $text);

        // Remove PGP markers
        $text = preg_replace('/^-----BEGIN PGP SIGNED MESSAGE-----\n(?:Hash: \w+)?\s+/iu', '', $text);
        $text = preg_replace('/^-----BEGIN PGP SIGNATURE-----.*?^-----END PGP SIGNATURE-----/mus', '', $text);

        // Remove unsubscribe links
        // Adjusted regex for PHP PCRE: use \z for absolute end of string
        $text = preg_replace('/^Unsubscribe: .+@.+(\n.+http:.+)?\s*\z/iu', '', $text);

        // Remove alias-style quotes marker (e.g. >>>>> "Someone" == <email> writes:)
        $text = preg_replace('/^.*>{5} "[^"\n]+" == .+ writes:/u', '', $text);

        // Change enclosed-style quotes format >>> / <<< to > prefixed lines
        $text = preg_replace_callback('/^>>> ?(.+?) ?>>>$\n([\s\S]+?)\n^<<< ?\1 ?<<<$/mu', function ($matches) {
            // Prefix each line with "> "
            return preg_replace('/^/mu', '> ', $matches[2]);
        }, $text);

        $text = preg_replace_callback('/^>{4,}[[:blank:]]*$\n([\s\S]+?)\n^<{4,}[[:blank:]]*$/mu', function ($matches) {
            // Prefix each line with "> "
            return preg_replace('/^/mu', '> ', $matches[1]);
        }, $text);

        // Fix all quote formats " | >" or "User >") to just ">"
        $text = preg_replace_callback('/^((?:[[:blank:]]*[[:alpha:]]*[>|])+)/mu', function ($matches) {
            // Replace occurrences of "letters>" or "|" with just ">"
            return preg_replace('/([[:alpha:]]+>|\|)/u', '>', $matches[1]);
        }, $text);


        // Fix embedded email markers that might span over multiple lines
        $multiLineFixRegexes = array_merge(
            EmbeddedEmailMatcher::ON_DATE_SOMEONE_WROTE_REGEXES,
            EmbeddedEmailMatcher::SOMEONE_WROTE_ON_DATE_REGEXES,
            EmbeddedEmailMatcher::getDateSomeoneWroteRegexes(),
            [EmbeddedEmailMatcher::DATE_SOMEONE_EMAIL_REGEX]
        );

        foreach ($multiLineFixRegexes as $regex) {
            $originalText = $text;

            $text = preg_replace_callback($regex, function($match) use($regex, $text, &$exit) {
                $matchedText = $match[0];
                if (substr_count($matchedText, "\n") <= 4) {
                    return preg_replace('/\n+[[:space:]]*/u', ' ', $matchedText);
                }
                return $matchedText;
            }, $text);
            if ($text === null) {
                trigger_error("preg_replace_callback failed in preprocess for regex: " . $regex, E_USER_WARNING);
                $text = $originalText;
            }
        }

        // Remove leading/trailing whitespaces
        return trim($text);
    }

    /**
     * Calculates the text that was removed (elided) during trimming.
     *
     * @param array $originalLines Lines before trimming.
     * @param array $trimmedLines Lines after trimming.
     * @return string The combined elided text.
     */
    private static function computeElided(array $originalLines, array $trimmedLines): string
    {
        $elided = [];
        $originalIndex = 0;
        $trimmedIndex = 0;
        $originalCount = count($originalLines);
        $trimmedCount = count($trimmedLines);

        while ($originalIndex < $originalCount) {
            if ($trimmedIndex < $trimmedCount && $originalLines[$originalIndex] === $trimmedLines[$trimmedIndex]) {
                $originalIndex++;
                $trimmedIndex++;
            } else {
                $elided[] = $originalLines[$originalIndex];
                $originalIndex++;
            }
        }

        return trim(implode("\n", $elided));
    }

    /**
     * Checks for a specific pattern indicating a reply added at the very end
     * after the quoted/embedded content.
     * Pattern: Starts with embedded 'b', followed by non-text 'e,q,h,d', ends with text 't'.
     * Example: bqqqetettt
     *
     * @param string $pattern The line type pattern string.
     * @return bool
     */
    private static function isReplyAtEnd(string $pattern): bool
    {
        return (bool) preg_match('/^b[^t]+t[et]*$/u', $pattern);
    }
}
