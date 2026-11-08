<?php

namespace Tok3\SectionEngineNewsletter\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Support\FormContext;

class SubscribeRequest extends FormRequest
{
    /** @var array<string, mixed> */
    public array $formContext = [];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $context = app(FormContext::class)->decrypt($this->input('context'));
        if ($context === null) {
            throw ValidationException::withMessages(['context' => __('section-engine-newsletter::newsletter.invalid_form')]);
        }
        $this->formContext = $context;
        app()->setLocale($context['locale']);
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:254', config('section-engine-newsletter.validate_dns') ? 'email:rfc,dns' : 'email:rfc'],
            'website' => ['nullable', 'string', 'max:0'],
            'consent' => config('section-engine-newsletter.require_checkbox') ? ['accepted'] : ['nullable'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => __('section-engine-newsletter::newsletter.email_required'),
            'email.email' => __('section-engine-newsletter::newsletter.email_invalid'),
            'email.max' => __('section-engine-newsletter::newsletter.email_invalid'),
            'email.string' => __('section-engine-newsletter::newsletter.email_invalid'),
            'consent.accepted' => __('section-engine-newsletter::newsletter.consent_required'),
            'website.max' => __('section-engine-newsletter::newsletter.invalid_form'),
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        if ($this->expectsJson() || $this->formContext === []) {
            parent::failedValidation($validator);
        }
        $context = $this->formContext;
        throw new HttpResponseException(
            redirect()->to(explode('#', $context['url'], 2)[0].'#newsletter-'.$context['form_id'])
                ->with('section-engine-newsletter.'.$context['form_id'], [
                    'context' => $context, 'errors' => $validator->errors()->toArray(), 'email' => $this->input('email'),
                ])
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $age = now()->timestamp - $this->formContext['rendered_at'];
            if ($age < config('section-engine-newsletter.min_fill_seconds') || $age > config('section-engine-newsletter.context_ttl_hours') * 3600) {
                $validator->errors()->add('context', __('section-engine-newsletter::newsletter.invalid_form'));
            }
            if (! NewsletterList::query()->where('key', $this->formContext['list'])->where('active', true)->exists()) {
                $validator->errors()->add('context', __('section-engine-newsletter::newsletter.invalid_form'));
            }
        });
    }
}
