<?php

namespace Tok3\SectionEngineNewsletter\Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;

class AccessibilityTest extends SubscribeTest
{
    public function test_german_validation_uses_package_messages(): void
    {
        $payload = $this->formPayload();
        $context = json_decode(Crypt::decryptString($payload['context']), true);
        $context['locale'] = 'de';
        $payload['context'] = Crypt::encryptString(json_encode($context));
        $payload['email'] = 'invalid';
        $this->postJson('/newsletter/subscribe', $payload)->assertUnprocessable()->assertJsonPath('errors.email.0', 'Bitte gib eine gültige E-Mail-Adresse ein.');
    }

    public function test_all_package_translation_keys_exist_in_both_languages(): void
    {
        foreach (['newsletter', 'admin'] as $file) {
            $en = Arr::dot(require __DIR__.'/../../resources/lang/en/'.$file.'.php');
            $de = Arr::dot(require __DIR__.'/../../resources/lang/de/'.$file.'.php');
            $this->assertSame(array_keys($en), array_keys($de));
            foreach ([...$en, ...$de] as $text) {
                $this->assertNotSame('', $text);
            }
        }
    }
}
