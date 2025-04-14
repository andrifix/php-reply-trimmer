# Email Reply Trimmer

This is a port of the [discourse/email_reply_trimmer](https://github.com/discourse/email_reply_trimmer) library. It's a small library to trim replies from plain text email.

# Install

Install via Composer:

```bash
composer require andrifix/email-reply-trimmer
```

# Usage

To trim replies:
```php
$trimmed_body = EmailReplyTrimmer::trim($email_body);
```

You can also split the trimmed content and the elided part (the removed reply):

```php
[$trimmedBody, $elidedContent] = EmailReplyTrimmer::trim($emailBody, true);
```

To extract the first embedded email section:

```php
$extracted = EmailReplyTrimmer::extractEmbeddedEmail($emailBodyWithEmbedded);
if ($extracted) {
    [$embeddedEmail, $beforeEmbedded] = $extracted;
    echo "Text Before Embedded Email: $beforeEmbedded\n";
    echo "Embedded Email $embeddedEmail:\n";
} else {
    echo "No embedded email found.";
}
```
