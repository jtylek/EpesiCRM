<?php

namespace App\Console\Commands;

use Epesi\Modules\CommonData\Facades\CommonData;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Models\EmailAddress;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportNewsletterContacts extends Command
{
    protected $signature = 'import:newsletter {file} {--dry-run : Show what would be imported without making changes}';

    protected $description = 'Import companies and contacts from the newsletter matching CSV and assign them to the Newsletter group';

    private int $companiesCreated = 0;

    private int $companiesUpdated = 0;

    private int $contactsCreated = 0;

    private int $contactsUpdated = 0;

    private int $skipped = 0;

    public function handle(): int
    {
        $file = $this->argument('file');
        $dryRun = $this->option('dry-run');

        if (! file_exists($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be made.');
        }

        $this->ensureNewsletterGroups($dryRun);

        $rows = $this->parseCsv($file);
        $this->info(count($rows).' rows read from CSV.');

        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $row) {
            $this->processRow($row, $dryRun);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Companies created: {$this->companiesCreated}");
        $this->info("Companies updated (newsletter group added): {$this->companiesUpdated}");
        $this->info("Contacts created: {$this->contactsCreated}");
        $this->info("Contacts updated (newsletter group added): {$this->contactsUpdated}");
        $this->info("Rows skipped (empty name): {$this->skipped}");

        return self::SUCCESS;
    }

    private function ensureNewsletterGroups(bool $dryRun): void
    {
        $companyGroups = CommonData::raw('Companies_Groups');
        $contactGroups = CommonData::raw('Contacts_Groups');

        if (! isset($companyGroups['newsletter'])) {
            $this->info('Adding "newsletter" to Companies_Groups...');
            if (! $dryRun) {
                CommonData::set('Companies_Groups/newsletter', 'Newsletter', readonly: false);
            }
        } else {
            $this->info('"newsletter" already exists in Companies_Groups.');
        }

        if (! isset($contactGroups['newsletter'])) {
            $this->info('Adding "newsletter" to Contacts_Groups...');
            if (! $dryRun) {
                CommonData::set('Contacts_Groups/newsletter', 'Newsletter', readonly: false);
            }
        } else {
            $this->info('"newsletter" already exists in Contacts_Groups.');
        }
    }

    private function parseCsv(string $file): array
    {
        $rows = [];
        $handle = fopen($file, 'r');

        if (! $handle) {
            return [];
        }

        $header = fgetcsv($handle);

        if (! $header) {
            fclose($handle);

            return [];
        }

        // Normalize header: trim BOM and whitespace
        $header = array_map(fn ($h) => trim(str_replace("\xEF\xBB\xBF", '', $h)), $header);

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) !== count($header)) {
                continue;
            }

            $row = array_combine($header, $data);
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function processRow(array $row, bool $dryRun): void
    {
        $companyName = trim($row['Firma'] ?? '');

        if ($companyName === '') {
            $this->skipped++;

            return;
        }

        // Clean up company name: remove surrounding quotes
        $companyName = trim($companyName, '"\'');

        $taxId = $this->cleanTaxId($row['NIP'] ?? '');
        $emails = $this->parseMultiValue($row['Email (uzupełnione)'] ?? '');
        $phones = $this->parseMultiValue($row['Telefon (uzupełniony)'] ?? '');
        $website = $this->parseFirstValue($row['Strona WWW'] ?? '');
        $address = trim($row['Adres firmy'] ?? '');
        $contactPersons = $this->parseMultiValue($row['Osoba kontaktowa'] ?? '');
        $positions = $this->parseMultiValue($row['Stanowisko'] ?? '');
        $originalContact = trim($row['Dane kontaktowe (oryginalne)'] ?? '');

        // Also extract contact info from the "Dane kontaktowe" column
        $originalEmails = $this->extractEmails($originalContact);
        $originalPhones = $this->extractPhones($originalContact);
        $originalName = $this->extractName($originalContact);

        // Try to find existing company
        $company = $this->findCompany($companyName, $taxId, array_merge($emails, $originalEmails));

        if ($company) {
            $this->addNewsletterGroup($company, $dryRun);
            $this->companiesUpdated++;
            $this->line(" <comment>Updated existing:</comment> {$companyName} (ID: {$company->id})");
        } else {
            $company = $this->createCompany($companyName, $taxId, $emails, $originalEmails, $phones, $originalPhones, $website, $address, $dryRun);
            $this->companiesCreated++;
            $id = $company?->id ?? 'N/A';
            $this->line(" <info>Created:</info> {$companyName} (ID: {$id})");
        }

        // Process contacts
        $this->processContacts($company, $contactPersons, $positions, $emails, $phones, $originalName, $originalEmails, $originalPhones, $dryRun);
    }

    private function findCompany(string $name, ?string $taxId, array $emails): ?Company
    {
        // Match by tax_id first (most reliable)
        if ($taxId) {
            $byTax = Company::where('tax_id', $taxId)->first();
            if ($byTax) {
                return $byTax;
            }
        }

        // Match by email
        foreach ($emails as $email) {
            $emailRecord = EmailAddress::where('value', mb_strtolower($email))
                ->where('owner_type', 'company')
                ->first();

            if ($emailRecord) {
                return Company::find($emailRecord->owner_id);
            }
        }

        // Match by normalized name
        $normalized = $this->normalizeName($name);
        $candidates = Company::all();

        foreach ($candidates as $candidate) {
            if ($this->normalizeName($candidate->company_name) === $normalized) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower($name);
        $name = trim($name, '"\'');

        // Remove common suffixes
        $suffixes = ['sp. z o.o.', 'sp. z o. o.', 'sp.z o.o.', 'spółka z ograniczoną odpowiedzialnością',
            'sp. j.', 'sp.j.', 'spółka jawna', 's.c.', 'sp. k.', 'sp.k.', 'sp. c.', 's.a.', 'sp. z o.o. sp. k.',
            'spółka akcyjna', 'spółka cywilna', 'spółka komandytowa'];

        foreach ($suffixes as $suffix) {
            $name = str_ireplace($suffix, '', $name);
        }

        // Remove extra whitespace and punctuation
        $name = preg_replace('/[^a-ząćęłńóśźżA-ZĄĆĘŁŃÓŚŹŻ0-9\s]/u', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    private function addNewsletterGroup(Company|Contact $record, bool $dryRun): void
    {
        $groups = $record->groups ?? [];

        if (in_array('newsletter', $groups)) {
            return;
        }

        $groups[] = 'newsletter';

        if (! $dryRun) {
            $record->update(['groups' => $groups]);
        }
    }

    private function createCompany(
        string $name,
        ?string $taxId,
        array $emails,
        array $originalEmails,
        array $phones,
        array $originalPhones,
        ?string $website,
        ?string $address,
        bool $dryRun,
    ): ?Company {
        if ($dryRun) {
            return null;
        }

        $company = Company::create([
            'company_name' => $name,
            'tax_id' => $taxId,
            'groups' => ['newsletter'],
        ]);

        // Add emails (merge both sources, deduplicate)
        $allEmails = array_unique(array_filter(array_merge($emails, $originalEmails)));
        $emailItems = [];
        foreach ($allEmails as $i => $email) {
            $email = mb_strtolower(trim($email));
            if ($email === '' || EmailAddress::where('value', $email)->exists()) {
                continue;
            }
            $emailItems[] = ['kind' => $i === 0 ? 'work' : 'other', 'value' => $email];
        }
        if ($emailItems) {
            $company->syncCollection('emails', $emailItems);
        }

        // Add phones
        $allPhones = array_unique(array_filter(array_merge($phones, $originalPhones)));
        $phoneItems = [];
        foreach ($allPhones as $phone) {
            $phone = trim($phone);
            if ($phone === '') {
                continue;
            }
            $phoneItems[] = ['kind' => 'work', 'value' => $phone];
        }
        if ($phoneItems) {
            $company->syncCollection('phones', $phoneItems);
        }

        // Add website
        if ($website) {
            $company->syncCollection('online_accounts', [['kind' => 'website', 'value' => $website]]);
        }

        // Parse and add address
        if ($address) {
            $parsedAddress = $this->parseAddress($address);
            if ($parsedAddress) {
                $company->syncCollection('addresses', [array_merge(['kind' => 'business'], $parsedAddress)]);
            }
        }

        return $company;
    }

    private function processContacts(
        ?Company $company,
        array $contactPersons,
        array $positions,
        array $emails,
        array $phones,
        ?string $originalName,
        array $originalEmails,
        array $originalPhones,
        bool $dryRun,
    ): void {
        // Build contact list from "Osoba kontaktowa" column
        $contacts = [];
        foreach ($contactPersons as $i => $person) {
            $person = trim($person);
            if ($person === '' || $this->looksLikeCompanyName($person)) {
                continue;
            }

            $names = $this->splitName($person);
            if (! $names) {
                continue;
            }

            $contacts[] = [
                'first_name' => $names['first'],
                'last_name' => $names['last'],
                'title' => $positions[$i] ?? null,
                'email' => $emails[$i] ?? null,
                'phone' => $phones[$i] ?? null,
            ];
        }

        // If no contacts from that column, try the original contact data
        if (empty($contacts) && $originalName) {
            $names = $this->splitName($originalName);
            if ($names) {
                $contacts[] = [
                    'first_name' => $names['first'],
                    'last_name' => $names['last'],
                    'title' => null,
                    'email' => $originalEmails[0] ?? null,
                    'phone' => $originalPhones[0] ?? null,
                ];
            }
        }

        foreach ($contacts as $contactData) {
            $this->processOneContact($company, $contactData, $dryRun);
        }
    }

    private function processOneContact(?Company $company, array $data, bool $dryRun): void
    {
        $firstName = trim($data['first_name']);
        $lastName = trim($data['last_name']);

        if ($firstName === '' || $lastName === '') {
            return;
        }

        // Try to find existing contact by email first
        $contact = null;
        if (! empty($data['email'])) {
            $email = mb_strtolower(trim($data['email']));
            $emailRecord = EmailAddress::where('value', $email)
                ->where('owner_type', 'contact')
                ->first();

            if ($emailRecord) {
                $contact = Contact::find($emailRecord->owner_id);
            }
        }

        // Try by name + company
        if (! $contact && $company) {
            $contact = Contact::where('first_name', $firstName)
                ->where('last_name', $lastName)
                ->where('company_id', $company->id)
                ->first();
        }

        // Try by name only (case-insensitive)
        if (! $contact) {
            $contact = Contact::whereRaw('LOWER(first_name) = ?', [mb_strtolower($firstName)])
                ->whereRaw('LOWER(last_name) = ?', [mb_strtolower($lastName)])
                ->first();
        }

        if ($contact) {
            $this->addNewsletterGroup($contact, $dryRun);
            $this->contactsUpdated++;

            // Link to company if not linked yet
            if (! $dryRun && $company && ! $contact->company_id && $contact->company_id !== $company->id) {
                $contact->update(['company_id' => $company->id]);
            }

            return;
        }

        // Create new contact
        if ($dryRun) {
            $this->contactsCreated++;
            $this->line("   <info>Would create contact:</info> {$firstName} {$lastName}");

            return;
        }

        $contact = Contact::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_id' => $company?->id,
            'title' => $data['title'] ?? null,
            'groups' => ['newsletter'],
        ]);

        $this->contactsCreated++;

        // Add email
        if (! empty($data['email'])) {
            $email = mb_strtolower(trim($data['email']));
            if (! EmailAddress::where('value', $email)->exists()) {
                $contact->syncCollection('emails', [['kind' => 'work', 'value' => $email]]);
            }
        }

        // Add phone
        if (! empty($data['phone'])) {
            $contact->syncCollection('phones', [['kind' => 'mobile', 'value' => trim($data['phone'])]]);
        }
    }

    private function parseMultiValue(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\s*\|\s*/', $value))));
    }

    private function parseFirstValue(string $value): ?string
    {
        $values = $this->parseMultiValue($value);

        // Prefer values that look like actual URLs (not eurolider.pl, linkedin, etc.)
        foreach ($values as $v) {
            if (Str::contains($v, ['eurolider', 'linkedin', 'facebook'])) {
                continue;
            }

            return $v;
        }

        return $values[0] ?? null;
    }

    private function cleanTaxId(?string $nip): ?string
    {
        if (! $nip) {
            return null;
        }

        $nip = preg_replace('/[^0-9]/', '', $nip);

        return $nip !== '' ? $nip : null;
    }

    private function extractEmails(string $text): array
    {
        if ($text === '') {
            return [];
        }

        preg_match_all('/[\w.+-]+@[\w.-]+\.\w{2,}/', $text, $matches);

        return array_map('mb_strtolower', $matches[0] ?? []);
    }

    private function extractPhones(string $text): array
    {
        if ($text === '') {
            return [];
        }

        preg_match_all('/(?:\+48\s?)?(?:\d[\s-]?){7,12}/', $text, $matches);

        return array_map(fn ($p) => trim(preg_replace('/\s+/', ' ', $p)), $matches[0] ?? []);
    }

    private function extractName(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        // Try to extract name from patterns like "Imię Nazwisko tel: ..." or "Imię Nazwisko, mail: ..."
        // Take the first line or text before tel/mail/email indicators
        $lines = preg_split('/[\n\r]+/', $text);
        $firstLine = trim($lines[0] ?? '');

        // Remove phone/email suffixes
        $name = preg_replace('/\s*(tel[:\.]?|mail[:\.]?|email[:\.]?|\d{3}[\s-]\d{3}[\s-]\d{3}|\+?\d{9,}).*/i', '', $firstLine);
        $name = preg_replace('/[,;].*/', '', $name);
        $name = trim($name);

        // Must look like a name (2-4 words, starts with uppercase)
        if (preg_match('/^[A-ZĄĆĘŁŃÓŚŹŻ][a-ząćęłńóśźż]+\s+[A-ZĄĆĘŁŃÓŚŹŻ][a-ząćęłńóśźż-]+/', $name)) {
            return $name;
        }

        return null;
    }

    private function splitName(string $fullName): ?array
    {
        $fullName = trim($fullName);

        // Skip if it looks like a company name rather than a person
        if ($this->looksLikeCompanyName($fullName)) {
            return null;
        }

        // Reject multi-person entries ("Marzena Jarych Paweł Jarych")
        if (preg_match('/^[A-ZĄĆĘŁŃÓŚŹŻ]\S+\s+[A-ZĄĆĘŁŃÓŚŹŻ]\S+\s+[A-ZĄĆĘŁŃÓŚŹŻ]\S+\s+[A-ZĄĆĘŁŃÓŚŹŻ]\S+$/u', $fullName)) {
            return null;
        }

        // Remove titles/prefixes
        $fullName = preg_replace('/^(dr|mgr|inż|prof|hab)\.\s*/i', '', $fullName);

        // Clean up
        $fullName = preg_replace('/\s+/', ' ', $fullName);

        $parts = explode(' ', $fullName);

        if (count($parts) < 2) {
            return null;
        }

        // Reject if more than 3 parts (likely garbage data)
        if (count($parts) > 3) {
            return null;
        }

        // In Polish: first name then last name
        $firstName = $parts[0];
        $lastName = implode(' ', array_slice($parts, 1));

        // Skip single-letter first names (initials like "D", "K", "L")
        if (mb_strlen($firstName) === 1) {
            return null;
        }

        return ['first' => $firstName, 'last' => $lastName];
    }

    private function looksLikeCompanyName(string $name): bool
    {
        $lower = mb_strtolower($name);

        $patterns = ['sp.', 'sp ', 'biuro', 'dział', 'księgow', 'kadry', 'laborat',
            'serwis', 'centrum', 'kontakt', 'ośrodek', 'osrodek', 'klub', 'restaur', 'technology',
            'cnc', 'lab', 'event', 'stolarstw', 'meble', 'agro ', 'trans ', 'nutri',
            'schilsner', 'tool-zap', 'metalbit', ' ig', 'dom pod', 'wczasow'];

        foreach ($patterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        // Reject entries that are duplicated words like "M.rudnicki M.rudnicki"
        $parts = explode(' ', $name);
        if (count($parts) === 2 && mb_strtolower($parts[0]) === mb_strtolower($parts[1])) {
            return true;
        }

        // Reject entries with " - " that look like "Company - Person" combinations
        if (str_contains($name, ' - ') && preg_match('/[A-Z][a-z]+ - [A-Z]/', $name)) {
            $beforeDash = trim(explode(' - ', $name)[0]);
            if ($this->looksLikeCompanyNameSimple($beforeDash)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeCompanyNameSimple(string $name): bool
    {
        $lower = mb_strtolower($name);
        $patterns = ['sp.', 'technology', 'cnc', 'center', 'centrum', 'group', 'system'];

        foreach ($patterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function parseAddress(?string $address): ?array
    {
        if (! $address) {
            return null;
        }

        // Take the first address if multiple separated by |
        $parts = explode('|', $address);
        $address = trim($parts[0]);

        if ($address === '') {
            return null;
        }

        $result = [];

        // Try to extract postal code and city: "XX-XXX City"
        if (preg_match('/(\d{2}-\d{3})\s+(.+?)(?:\s*,\s*Polska)?$/u', $address, $m)) {
            $result['postal_code'] = $m[1];
            $result['city'] = trim($m[2]);

            // Everything before the postal code is the street
            $street = trim(str_replace($m[0], '', $address), " ,\t\n\r");
            if ($street) {
                $result['address_1'] = $this->cleanStreet($street);
            }
        } elseif (preg_match('/^(.+?),\s*(\d{2}-\d{3})\s+(.+)/u', $address, $m)) {
            $result['address_1'] = $this->cleanStreet(trim($m[1]));
            $result['postal_code'] = $m[2];
            $result['city'] = trim($m[3], " ,\t\n\r");
        } else {
            // Can't parse structured address, store as address_1
            $result['address_1'] = $address;
        }

        $result['country'] = 'PL';

        return $result;
    }

    private function cleanStreet(string $street): string
    {
        // Remove company names or prefixes that got mixed in
        $street = preg_replace('/^[A-Z][\w\s.]+(?:sp\.|Sp\.)[^,]*,\s*/u', '', $street);

        // Remove "ul." prefix if duplicated info
        return trim($street);
    }
}
