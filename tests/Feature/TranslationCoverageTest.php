<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TranslationCoverageTest extends TestCase
{
    public function test_every_literal_backend_message_has_a_spanish_translation(): void
    {
        $translations = json_decode(file_get_contents(lang_path('es.json')), true, flags: JSON_THROW_ON_ERROR);
        $missing = [];

        foreach ([app_path(), base_path('routes')] as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                preg_match_all("/__\\(\\s*'((?:\\\\.|[^'\\\\])*)'/", file_get_contents($file->getPathname()), $matches);
                foreach ($matches[1] as $message) {
                    $message = str_replace(["\\'", '\\\\'], ["'", '\\'], $message);
                    if (! isset($translations[$message]) || trim($translations[$message]) === '') {
                        $missing[] = $message;
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'Backend messages are missing from lang/es.json.');
    }

    #[DataProvider('localizedMessages')]
    public function test_messages_written_in_either_language_have_the_expected_translation(string $locale, string $source, string $expected): void
    {
        app()->setLocale($locale);

        $this->assertSame($expected, __($source));
    }

    public static function localizedMessages(): array
    {
        return [
            ['es', 'Expense created.', 'Gasto creado.'],
            ['es', 'Bank reconciliation', 'Conciliación bancaria'],
            ['en', 'Alerta actualizada.', 'Alert updated.'],
            ['en', 'Proyecto creado correctamente.', 'Project created successfully.'],
        ];
    }

    public function test_standard_validation_messages_are_available_in_spanish(): void
    {
        app()->setLocale('es');
        $validator = validator([], ['expense_date' => ['required']]);

        $this->assertSame('El campo fecha del gasto es obligatorio.', $validator->errors()->first('expense_date'));
    }
}
