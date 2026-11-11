<?php

use Epesi\Modules\MigrationWizard\Orchestration\OperatorChoices;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MigrationWizardOperatorChoicesTest extends TestCase
{
    public function test_no_options_means_no_choices(): void
    {
        self::assertSame(OperatorChoices::none(), OperatorChoices::fromOptions(null, null, false));
    }

    public function test_exact_names_are_parsed_trimmed_and_deduplicated(): void
    {
        $choices = OperatorChoices::fromOptions(' custom_invoice, data/Custom_Invoice,custom_invoice,,@unknown', 'old_cache,data/*', true);

        self::assertSame(['custom_invoice', 'data/Custom_Invoice', '@unknown'], $choices['preserve']);
        self::assertSame(['old_cache', 'data/*'], $choices['discard']);
        self::assertTrue($choices['core_only']);
    }

    /** @return array<string, array{0: string}> */
    public static function badNames(): array
    {
        return ['wildcard' => ['custom_*'], 'path traversal' => ['data/../x'], 'quote' => ['a"b'], 'space' => ['a b'], 'deep path' => ['data/a/b/c'], 'backtick' => ['`x`'], 'glob everything' => ['*']];
    }

    #[DataProvider('badNames')]
    public function test_only_exact_names_are_accepted(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        OperatorChoices::fromOptions($name, null, false);
    }

    #[DataProvider('badNames')]
    public function test_a_discard_must_be_an_exact_name_too(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        OperatorChoices::fromOptions(null, $name, false);
    }

    public function test_the_unknown_token_can_preserve_but_never_discard(): void
    {
        self::assertSame(['@unknown'], OperatorChoices::fromOptions('@unknown', null, false)['preserve']);

        $this->expectException(InvalidArgumentException::class);
        OperatorChoices::fromOptions(null, '@unknown', false);
    }

    public function test_the_same_name_cannot_be_both_preserved_and_discarded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OperatorChoices::fromOptions('a_table', 'a_table', false);
    }

    public function test_a_given_option_replaces_the_stored_one_and_an_omitted_one_keeps_it(): void
    {
        $stored = ['preserve' => ['a'], 'discard' => ['b'], 'core_only' => true];

        $merged = OperatorChoices::merge($stored, ['preserve' => 'c', 'discard' => null, 'core_only' => false]);

        self::assertSame(['preserve' => ['c'], 'discard' => ['b'], 'core_only' => true], $merged);
    }

    public function test_a_merge_still_refuses_an_overlap_with_what_is_stored(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OperatorChoices::merge(['preserve' => ['a'], 'discard' => [], 'core_only' => false], ['preserve' => null, 'discard' => 'a', 'core_only' => false]);
    }

    public function test_a_missing_or_odd_stored_value_is_no_choices(): void
    {
        self::assertSame(OperatorChoices::none(), OperatorChoices::normalise(null));
        self::assertSame(OperatorChoices::none(), OperatorChoices::normalise('x'));
        self::assertSame(['preserve' => ['a'], 'discard' => [], 'core_only' => true], OperatorChoices::normalise(['preserve' => ['a'], 'core_only' => 1]));
    }

    public function test_business_file_areas_cannot_be_discarded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OperatorChoices::fromOptions(null, 'data/CRM_Mail', false);
    }
}
