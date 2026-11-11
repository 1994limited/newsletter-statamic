<?php

namespace NineteenNinetyFour\Newsletter\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use NineteenNinetyFour\Newsletter\SubscriberStatus;
use Statamic\Facades\Site;

/**
 * One email address on the list.
 *
 * @property string $id
 * @property string $email
 * @property string|null $name
 * @property SubscriberStatus $status
 * @property string|null $site
 * @property string|null $locale
 * @property string|null $consent
 * @property string|null $source_url
 * @property Carbon|null $confirmation_sent_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $unsubscribed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Subscriber extends Model
{
    use HasUlids;

    protected $table = 'newsletter_subscribers';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => SubscriberStatus::class,
            'confirmation_sent_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('newsletter.database.connection') ?? parent::getConnectionName();
    }

    public static function normaliseEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function scopeSubscribed(Builder $query): void
    {
        $query->where('status', SubscriberStatus::Subscribed->value);
    }

    public function isSubscribed(): bool
    {
        return $this->status === SubscriberStatus::Subscribed;
    }

    public function isPending(): bool
    {
        return $this->status === SubscriberStatus::Pending;
    }

    public function siteObject(): \Statamic\Sites\Site
    {
        return ($this->site ? Site::get($this->site) : null) ?? Site::default();
    }

    /**
     * The link in the confirmation email: signed, and good for a few days.
     */
    public function confirmUrl(): string
    {
        return URL::temporarySignedRoute(
            'statamic.newsletter.confirm',
            now()->addHours(max(1, (int) config('newsletter.confirm_hours', 72))),
            ['subscriber' => $this->id],
        );
    }

    /**
     * Their own unsubscribe link, for the emails you send (it's in the
     * CSV). It doesn't expire: emails are read late.
     */
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('statamic.newsletter.unsubscribe', ['subscriber' => $this->id]);
    }
}
