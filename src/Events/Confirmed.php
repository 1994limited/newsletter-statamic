<?php

namespace NineteenNinetyFour\Newsletter\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\Newsletter\Models\Subscriber;

/**
 * Someone followed the link in the confirmation email: they're on the list.
 */
class Confirmed
{
    use Dispatchable;

    public function __construct(public Subscriber $subscriber) {}
}
