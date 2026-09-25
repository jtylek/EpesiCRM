<?php

/**
 * What the Archive button checks before it moves anything, against Epesi's
 * database: whether a message would be linked to anything, and whether it is
 * archived already.
 *
 * It must agree with Epesi\Modules\Mail\Services\ContactMatcher and
 * MailArchiver, which do the archiving afterwards. The test suite runs both
 * over the same records. Plain PHP, like epesi_sso_ticket, for that test.
 */
final class epesi_archive_matcher
{
    /**
     * Whether any of a message's From, To and Cc addresses belongs to a
     * contact or company, by its own e-mail or an extra address — Epesi's
     * look_contact(). Like ContactMatcher: no visibility rule, deleted
     * records left out, and your own contact counts.
     *
     * @param  array<int, string>  $emails
     * @param  callable(string, array<int, mixed>): ?array<string, mixed>  $selectOne  first row or null
     */
    public static function found(array $emails, callable $selectOne): bool
    {
        $emails = array_values(array_unique(array_filter(array_map(
            fn ($email): string => mb_strtolower(trim((string) $email)),
            $emails,
        ))));

        if ($emails === []) {
            return false;
        }

        $in = implode(', ', array_fill(0, count($emails), '?'));

        $queries = [
            "SELECT id FROM contacts WHERE deleted_at IS NULL AND lower(email) IN ({$in}) LIMIT 1",
            "SELECT id FROM companies WHERE deleted_at IS NULL AND lower(email) IN ({$in}) LIMIT 1",
            'SELECT a.id FROM epesi_mail_addresses a'
                ." LEFT JOIN contacts c ON a.addressable_type = 'contact' AND c.id = a.addressable_id AND c.deleted_at IS NULL"
                ." LEFT JOIN companies k ON a.addressable_type = 'company' AND k.id = a.addressable_id AND k.deleted_at IS NULL"
                ." WHERE lower(a.email) IN ({$in}) AND (c.id IS NOT NULL OR k.id IS NOT NULL) LIMIT 1",
        ];

        foreach ($queries as $sql) {
            if ($selectOne($sql, $emails)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Which of these Message-IDs are already in the archive, whoever archived
     * them — Epesi's "Message already archived". A deleted copy doesn't
     * count: archiving again restores it (MailArchiver::existing()).
     *
     * @param  array<int, string>  $messageIds  with or without their <angle brackets>
     * @param  callable(string, array<int, mixed>): ?array<string, mixed>  $selectOne  first row or null
     * @return array<int, string> the archived ones, as given
     */
    public static function archived(array $messageIds, callable $selectOne): array
    {
        return array_values(array_filter($messageIds, function ($id) use ($selectOne): bool {
            $id = trim((string) $id, " \t<>");

            return $id !== '' && $selectOne('SELECT id FROM epesi_mails WHERE deleted_at IS NULL AND message_id = ? LIMIT 1', [$id]) !== null;
        }));
    }
}
