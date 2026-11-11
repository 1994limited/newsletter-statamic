<?php

namespace NineteenNinetyFour\Newsletter\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\Newsletter\Models\Subscriber;

/**
 * Someone unsubscribed.
 */
class Unsubscribed
{
    use Dispatchable;

    public function __construct(public Subscriber $subscriber) {}
}
