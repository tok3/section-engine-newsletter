<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Illuminate\Support\Arr;
use Tok3\SectionEngineNewsletter\Tests\TestCase;

class TranslationsTest extends TestCase
{
    public function test_spanish_translation_files_are_complete(): void
    {
        foreach (['newsletter', 'admin'] as $group) {
            $englishPath = __DIR__.'/../../resources/lang/en/'.$group.'.php';
            $spanishPath = __DIR__.'/../../resources/lang/es/'.$group.'.php';
            $this->assertFileExists($spanishPath);
            if (! is_file($spanishPath)) {
                continue;
            }

            $this->assertSame(array_keys(Arr::dot(require $englishPath)), array_keys(Arr::dot(require $spanishPath)));
        }
    }
}
