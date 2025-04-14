<?php

declare(strict_types=1);

namespace Tests\EmailReplyTrimmer\Matcher;

use EmailReplyTrimmer\Matcher\EmbeddedEmailMatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EmbeddedEmailMatcherTest extends TestCase
{
    private const MATCHER_FIXTURES_DIR = __DIR__ . '/_data/matchers';

    #[Test] public function matchDoesNotHangWhenNoEmbeddedEmailIsFound(): void
    {
        $filename = 'does_not_contain_embedded_email.txt';
        $path = self::MATCHER_FIXTURES_DIR . '/' . $filename;

        $this->assertFileExists($path, "Matcher fixture file not found: $path");
        $content = file_get_contents($path);
        $this->assertIsString($content, "Failed to read matcher fixture file: $path");
        $content = trim($content); // Match Ruby test setup

        // The main check is that this call completes without timeout/fatal error.
        $result = EmbeddedEmailMatcher::match($content);

        $this->assertFalse($result, "Expected match() to return false for $filename");

        // If the call completed and assertion passed, the non-hanging aspect is verified.
        $this->assertTrue(true, "Matcher::match completed without fatal errors or hangs.");
    }
}
