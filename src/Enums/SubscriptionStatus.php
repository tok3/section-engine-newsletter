<?php

namespace Tok3\SectionEngineNewsletter\Enums;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Unsubscribed = 'unsubscribed';

    public function label(): string
    {
        return __('section-engine-newsletter::newsletter.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning', self::Confirmed => 'success', self::Unsubscribed => 'gray'
        };
    }
}
