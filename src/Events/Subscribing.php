<?php

namespace NineteenNinetyFour\Newsletter\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\Newsletter\Models\Subscriber;

/**
 * Before a sign-up is saved. Return false from a listener to turn it away (quietly: the visitor is told it worked).
 */
class Subscribing
{
    use Dispatchable;

    public function __construct(public Subscriber $subscriber) {}
}
