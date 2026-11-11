<?php

return [

    'subscribed' => 'Thanks! Check your inbox for an email to confirm your subscription.',
    'confirmed' => 'Thanks for confirming. :email is on the list.',
    'already_confirmed' => 'That’s already confirmed: :email is on the list.',
    'unsubscribed' => ':email won’t get the newsletter any more.',
    'already_unsubscribed' => 'You’re not on the list.',

    'errors' => [
        'email' => 'Please enter a valid email address.',
        'name' => 'Please enter your name.',
        'consent' => 'Please tick the box to say you’d like the newsletter.',
        'captcha' => 'We couldn’t check you’re not a robot. Please try again.',
        'expired' => 'This link has expired. Please sign up again, and we’ll send you a new one.',
    ],

    'status' => [
        'pending' => 'Waiting to confirm',
        'subscribed' => 'Subscribed',
        'unsubscribed' => 'Unsubscribed',
    ],

    'defaults' => [
        'consent_text' => 'Email me the newsletter. I can unsubscribe at any time.',
        'confirm_subject' => 'Please confirm your subscription',
        'confirm_heading' => 'One more step',
        'confirm_intro' => 'Please confirm you’d like the :site newsletter at :email.',
        'confirm_button' => 'Yes, subscribe me',
    ],

    'form' => [
        'email' => 'Email address',
        'name' => 'Name',
        'optional' => 'optional',
        'button' => 'Subscribe',
        'honeypot' => 'Leave this empty',
    ],

    'email' => [
        'link_expires' => 'The link works for :hours hours. If you didn’t ask for this, ignore this email and you won’t hear from us.',
    ],

    'notify' => [
        'subject' => 'New newsletter subscriber: :email',
        'body' => ':email confirmed their newsletter sign-up.',
        'count' => ':count confirmed subscribers in all.',
    ],

    'page' => [
        'confirm_title' => 'Confirm your subscription',
        'confirm_text' => 'Send the newsletter to :email?',
        'confirmed_title' => 'You’re subscribed',
        'expired_title' => 'This link has expired',
        'unsubscribe_title' => 'Unsubscribe',
        'unsubscribe_text' => 'Stop sending the newsletter to :email?',
        'unsubscribe_button' => 'Unsubscribe',
        'unsubscribed_title' => 'You’re unsubscribed',
        'back' => 'Back to :site',
    ],

    'cp' => [
        'title' => 'Newsletter',
    ],

    'permissions' => [
        'group' => 'Newsletter',
        'view' => 'See the newsletter list',
        'export' => 'Download it as CSV',
        'manage' => 'Delete subscribers',
    ],

];
