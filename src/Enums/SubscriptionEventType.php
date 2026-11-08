<?php

namespace Tok3\SectionEngineNewsletter\Enums;

enum SubscriptionEventType: string
{
    case Requested = 'requested';
    case ConfirmationSent = 'confirmation_sent';
    case Confirmed = 'confirmed';
    case Unsubscribed = 'unsubscribed';
    case ResubscribeRequested = 'resubscribe_requested';
    case AdminAction = 'admin_action';
}
