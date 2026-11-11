<?php

namespace NineteenNinetyFour\Newsletter;

enum SubscriberStatus: string
{
    /** Signed up; the confirmation link hasn't been followed yet. */
    case Pending = 'pending';

    /** Confirmed: on the list. */
    case Subscribed = 'subscribed';

    case Unsubscribed = 'unsubscribed';

    public function label(): string
    {
        return __('newsletter::messages.status.'.$this->value);
    }
}
