# Email Reply Trimmer

This is a port of the [discourse/email_reply_trimmer](https://github.com/discourse/email_reply_trimmer) library. It's a small library to trim replies from plain text email.

# Install

Install via Composer:

composer require andrifix/email-reply-trimmer

# Usage

To trim replies:

$trimmed_body = EmailReplyTrimmer::trim($email_body);

