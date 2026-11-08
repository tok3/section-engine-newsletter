<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Tok3\SectionEngine\Support\LiveTagRegistry;
use Tok3\SectionEngineNewsletter\Tests\TestCase;

class PackageBootTest extends TestCase
{
    public function test_core_registry_contains_newsletter_provider(): void
    {
        $this->assertTrue(app(LiveTagRegistry::class)->has('newsletter'));
    }
}
