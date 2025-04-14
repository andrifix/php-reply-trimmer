<?php

declare(strict_types=1);

namespace EmailReplyTrimmer\Matcher;

/**
 * Matches common mobile/email client signature lines.
 */
class SignatureMatcher
{
    // Envoyé depuis mon iPhone
    // Von meinem Mobilgerät gesendet
    // Diese Nachricht wurde von meinem Android-Mobiltelefon mit K-9 Mail gesendet.
    // Nik from mobile
    // From My Iphone 6
    // Sent via mobile
    // Sent with Airmail
    // Sent from Windows Mail
    // Sent from my TI-85
    // <<sent by galaxy>>
    // (sent from a phone)
    // (Sent from mobile device)
    // 從我的 iPhone 傳送
    private const SIGNATURE_REGEXES = [
        // Chinese
        '/^[[:blank:]]*從我的 iPhone 傳送/iu',
        // English
        '/^[[:blank:]]*[[:word:]]+ from mobile/iu',
        '/^[[:blank:]]*[\(<]*Sent (from|via|with|by) .+[\)>]*/iu',
        '/^[[:blank:]]*From my .{1,20}/iu',
        '/^[[:blank:]]*Get Outlook for /iu',
        // French
        '/^[[:blank:]]*Envoyé depuis (mon|Yahoo Mail)/iu',
        // German
        '/^[[:blank:]]*Von meinem .+ gesendet/iu',
        '/^[[:blank:]]*Diese Nachricht wurde von .+ gesendet/iu',
        // Italian
        '/^[[:blank:]]*Inviato da /iu',
        // Norwegian
        '/^[[:blank:]]*Sendt fra min /iu',
        // Portuguese
        '/^[[:blank:]]*Enviado do meu /iu',
        // Spanish
        '/^[[:blank:]]*Enviado desde mi /iu',
        // Dutch
        '/^[[:blank:]]*Verzonden met /iu',
        '/^[[:blank:]]*Verstuurd vanaf mijn /iu',
        // Swedish
        '/^[[:blank:]]*från min /iu',
    ];

    public static function match(string $line): bool
    {
        // Remove any markdown links
        $stripped = preg_replace('/\[([^\]]+)\]\([^\)]+\)/u', '$1', $line);
        if ($stripped === null) {
            $stripped = $line;
        }

        foreach (self::SIGNATURE_REGEXES as $regex) {
            if (preg_match($regex, $stripped)) {
                return true;
            }
        }
        return false;
    }
}
