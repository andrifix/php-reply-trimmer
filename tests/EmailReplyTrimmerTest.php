<?php

declare(strict_types=1);

namespace Tests\EmailReplyTrimmer;

use EmailReplyTrimmer\EmailReplyTrimmer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EmailReplyTrimmerTest extends TestCase
{
    private const FIXTURE_BASE_PATH = __DIR__ . '/_data';
    private const EMAILS_DIR = self::FIXTURE_BASE_PATH . '/emails';
    private const TRIMMED_DIR = self::FIXTURE_BASE_PATH . '/trimmed';
    private const ELIDED_DIR = self::FIXTURE_BASE_PATH . '/elided';
    private const EMBEDDED_DIR = self::FIXTURE_BASE_PATH . '/embedded';
    private const BEFORE_DIR = self::FIXTURE_BASE_PATH . '/before';

    private const EMBEDDED_TEST_FILES = [
        'email_headers_1.txt',
        'email_headers_2.txt',
        'email_headers_3.txt',
        'email_headers_4.txt',
        'embedded_email_10.txt',
        'embedded_email_german_3.txt',
        'embedded_email_spanish_2.txt',
        'forwarded_message.txt',
        'forwarded_gmail.txt',
        'forwarded_apple.txt',
    ];

    public static function provideEmailFiles(): \Generator
    {
        $files = glob(self::EMAILS_DIR . '/*.txt');
        if ($files === false) {
            self::fail('Could not read emails directory: ' . self::EMAILS_DIR);
        }
        foreach ($files as $path) {
            yield [basename($path)];
        }
    }

    public static function provideEmbeddedEmailFiles(): \Generator
    {
        foreach (self::EMBEDDED_TEST_FILES as $filename) {
            $emailPath = self::EMAILS_DIR . '/' . $filename;
            if (!file_exists($emailPath)) {
                self::fail("Required email fixture file missing for embedded test: $emailPath");
            }
            yield [$filename];
        }
    }

    #[Test] public function fixtureFilesExist(): void
    {
        $emailFiles = iterator_to_array(self::provideEmailFiles());
        $embeddedFiles = iterator_to_array(self::provideEmbeddedEmailFiles());

        $this->assertNotEmpty($emailFiles, 'No email fixture files found in ' . self::EMAILS_DIR);
        $this->assertNotEmpty($embeddedFiles, 'No embedded test case files generated (check EMBEDDED_TEST_FILES and email fixtures).');

        if (!empty($emailFiles)) {
            $firstFilename = $emailFiles[0][0];
            $this->assertFileExists(self::TRIMMED_DIR . '/' . $firstFilename, "Trimmed file missing for $firstFilename");
            $this->assertFileExists(self::ELIDED_DIR . '/' . $firstFilename, "Elided file missing for $firstFilename");
        }
        if (!empty($embeddedFiles)) {
            $firstFilename = $embeddedFiles[0][0];
            $this->assertFileExists(self::EMBEDDED_DIR . '/' . $firstFilename, "Embedded file missing for $firstFilename");
            $this->assertFileExists(self::BEFORE_DIR . '/' . $firstFilename, "Before file missing for $firstFilename");
        }
    }

    #[Test] public function normalizeLineEndingsEmailHasWindowsLineEndings(): void
    {
        $content = $this->getEmailContent('normalize_line_endings.txt');
        $this->assertStringContainsString("\r\n", $content, "Original normalize_line_endings.txt should contain Windows line endings.");
    }

    #[Test] #[DataProvider('provideEmailFiles')] public function trimAndElide(string $filename): void
    {
        $emailContent = $this->getEmailContent($filename);
        $expectedTrimmed = $this->getTrimmedContent($filename);
        $expectedElided = $this->getElidedContent($filename);

        $actualTrimmed = EmailReplyTrimmer::trim($emailContent);
        $this->assertEquals($expectedTrimmed, $actualTrimmed, "[TRIMMED] Failed for email: $filename");

        $result = EmailReplyTrimmer::trim($emailContent, true);
        $this->assertIsArray($result, "[SPLIT] Expected array result for email: $filename");
        $this->assertCount(2, $result, "[SPLIT] Expected array with 2 elements for email: $filename");

        $actualSplitTrimmed = $result[0] ?? null;
        $actualElided = $result[1] ?? null;

        $this->assertEquals($expectedTrimmed, $actualSplitTrimmed, "[SPLIT-TRIMMED] Failed for email: $filename");
        $this->assertEquals($expectedElided, $actualElided, "[ELIDED] Failed for email: $filename");

        $this->assertNull(EmailReplyTrimmer::trim(null), "[TRIMMED] Null input");
        $this->assertEquals([null, null], EmailReplyTrimmer::trim(null, true), "[ELIDED] Null input");

        $this->assertNull(EmailReplyTrimmer::trim(" "), "[TRIMMED] Whitespace input");
        $this->assertEquals([null, null], EmailReplyTrimmer::trim("   \n \t ", true), "[ELIDED] Whitespace input");
    }

    #[Test] #[DataProvider('provideEmbeddedEmailFiles')] public function extractEmbeddedEmail(string $filename): void
    {
        $emailContent = $this->getEmailContent($filename);
        $expectedEmbedded = $this->getEmbeddedContent($filename);
        $expectedBefore = $this->getBeforeContent($filename);

        $result = EmailReplyTrimmer::extractEmbeddedEmail($emailContent);

        $this->assertIsArray($result, "Expected array result for embedded extraction: $filename");
        $this->assertCount(2, $result, "Expected array with 2 elements for embedded extraction: $filename");

        $actualEmbedded = $result[0] ?? null;
        $actualBefore = $result[1] ?? null;

        $this->assertEquals($expectedEmbedded, $actualEmbedded, "[EMBEDDED] Failed for email: $filename");
        $this->assertEquals($expectedBefore, $actualBefore, "[BEFORE] Failed for email: $filename");
    }

    #[Test] public function trimDoesNotHangOnRepetitivePatterns(): void
    {
        $problematicPatternContent = 'b' . str_repeat('q e q d q ', 200) . 't';
        $nonMatchingRepetitive = str_repeat('> quoted line ' . "\n" . 'another quoted line' . "\n", 200);

        EmailReplyTrimmer::trim($problematicPatternContent);
        EmailReplyTrimmer::trim($nonMatchingRepetitive);

        $this->assertTrue(true, "Trim completed without fatal errors or hangs.");
    }

    private function getFixtureContent(string $typeDir, string $filename): string
    {
        $path = $typeDir . '/' . $filename;
        static::assertFileExists($path, "Fixture file not found: $path");
        $content = file_get_contents($path);
        static::assertIsString($content, "Failed to read fixture file: $path");
        return trim($content);
    }

    private function getEmailContent(string $filename): string
    {
        return $this->getFixtureContent(self::EMAILS_DIR, $filename);
    }

    private function getTrimmedContent(string $filename): string
    {
        return $this->getFixtureContent(self::TRIMMED_DIR, $filename);
    }

    private function getElidedContent(string $filename): string
    {
        return $this->getFixtureContent(self::ELIDED_DIR, $filename);
    }

    private function getEmbeddedContent(string $filename): string
    {
        return $this->getFixtureContent(self::EMBEDDED_DIR, $filename);
    }

    private function getBeforeContent(string $filename): string
    {
        return $this->getFixtureContent(self::BEFORE_DIR, $filename);
    }
}
