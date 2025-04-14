<?php

declare(strict_types=1);

namespace EmailReplyTrimmer\Matcher;

/**
 * Matches standard email header lines (From:, To:, Subject:, Date:, etc.).
 */
class EmailHeaderMatcher
{
    private const EMAIL_HEADERS_WITH_DATE_MARKERS = [
        ["Sendt"], // Norwegian
        ["Sent", "Date"], // English
        ["Date", "Le"], // French
        ["Gesendet"], // German
        ["Enviada em"], // Portuguese
        ["Enviado"], // Spanish
        ["Fecha"], // Spanish (Mexican)
        ["Data"], // Italian
        ["Datum"], // Dutch
        ["Skickat"], // Swedish
        ["发送时间"], // Chinese
    ];

    private const EMAIL_HEADERS_WITH_TEXT_MARKERS = [
        ["Fra", "Til", "Emne"], // Norwegian
        ["From", "To", "Cc", "Reply-To", "Subject"], // English
        ["De", "Expéditeur", "À", "Destinataire", "Répondre à", "Objet"], // French
        ["Von", "An", "Betreff"], // German
        ["De", "Para", "Assunto"], // Portuguese
        ["De", "Para", "Asunto"], // Spanish
        ["Da", "Risposta", "A", "Oggetto"], // Italian
        ["Van", "Beantwoorden - Aan", "Aan", "Onderwerp"], // Dutch
        ["Från", "Till", "Ämne"], // Swedish
        ["发件人", "收件人", "主题"], // Chinese
    ];

    /** @var array<string>|null Combined list of all header regexes */
    private static ?array $allHeaderRegexes = null;

    /**
     * Get all combined email header regexes.
     * Lazily initializes the combined list.
     *
     * @return array<string>
     */
    private static function getAllRegexes(): array
    {
        if (self::$allHeaderRegexes === null) {
            $dateRegexes = [];
            foreach (self::EMAIL_HEADERS_WITH_DATE_MARKERS as $headers) {
                $quotedHeaders = array_map(fn($h) => preg_quote($h, '/'), $headers);
                $pattern = implode('|', $quotedHeaders);
                $dateRegexes[] = '/^[[:blank:]*]*(?:' . $pattern . ')[[:blank:]*]*:.*\d+/u';
            }

            $textRegexes = [];
            foreach (self::EMAIL_HEADERS_WITH_TEXT_MARKERS as $headers) {
                $quotedHeaders = array_map(fn($h) => preg_quote($h, '/'), $headers);
                $pattern = implode('|', $quotedHeaders);
                $textRegexes[] = '/^[[:blank:]*]*(?:' . $pattern . ')[[:blank:]*]*:.*[[:word:]]+/iu';
            }

            self::$allHeaderRegexes = array_merge($dateRegexes, $textRegexes);
        }
        return self::$allHeaderRegexes;
    }

    public static function match(string $line): bool
    {
        foreach (self::getAllRegexes() as $regex) {
            if (preg_match($regex, $line)) {
                return true;
            }
        }
        return false;
    }
}
