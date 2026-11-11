<?php

namespace NineteenNinetyFour\Newsletter\Http\Controllers\CP;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use NineteenNinetyFour\Newsletter\Models\Subscriber;
use NineteenNinetyFour\Newsletter\Newsletter;
use NineteenNinetyFour\Newsletter\Settings;
use NineteenNinetyFour\Newsletter\SubscriberStatus;
use Statamic\Facades\Addon;
use Statamic\Facades\CP\Toast;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tools → Newsletter: the list, searched and filtered by status, and
 * downloaded as CSV.
 */
class SubscribersController extends CpController
{
    public function __construct(Request $request, protected Newsletter $newsletter)
    {
        parent::__construct($request);
    }

    public function index(Request $request): Response
    {
        $this->authorize('view newsletter');

        $search = $this->search($request);
        $status = $this->status($request);

        $paginator = $this->query($search, $status)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $user = User::current();

        return Inertia::render('newsletter::Index', [
            'search' => $search,
            'status' => $status?->value,
            'statuses' => collect(SubscriberStatus::cases())->map(fn (SubscriberStatus $s) => ['value' => $s->value, 'label' => $s->label()])->all(),
            'subscribers' => collect($paginator->items())->map(fn (Subscriber $subscriber) => [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'name' => $subscriber->name,
                'status' => $subscriber->status->value,
                'status_label' => $subscriber->status->label(),
                'site' => $subscriber->site,
                'signed_up' => $subscriber->created_at?->toDayDateTimeString(),
                'confirmed' => $subscriber->confirmed_at?->toDayDateTimeString(),
                'unsubscribed' => $subscriber->unsubscribed_at?->toDayDateTimeString(),
                'delete_url' => cp_route('newsletter.destroy', $subscriber->id),
            ])->all(),
            'pagination' => [
                'page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'counts' => $this->counts(),
            'askName' => Settings::askName() || Subscriber::query()->whereNotNull('name')->exists(),
            'canExport' => $user->can('export newsletter'),
            'canManage' => $user->can('manage newsletter'),
            'urls' => [
                'index' => cp_route('newsletter.index'),
                'export' => cp_route('newsletter.export'),
                'settings' => $this->settingsUrl(),
            ],
        ]);
    }

    /**
     * The list as a spreadsheet (CSV): confirmed subscribers by default,
     * ?status=all for everyone, or a status; and ?search= to match the page.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export newsletter');

        $all = $request->query('status') === 'all';
        $status = $all ? null : ($this->status($request) ?? SubscriberStatus::Subscribed);
        $query = $this->query($this->search($request), $status)->orderBy('created_at')->orderBy('id');
        $name = 'newsletter-'.($all ? 'all' : $status->value).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            // A byte-order mark, so Excel reads names with accents properly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $this->newsletter->csvHeadings(), escape: '');

            $query->chunk(500, function (Collection $subscribers) use ($out) {
                foreach ($subscribers as $subscriber) {
                    fputcsv($out, $this->newsletter->csvRow($subscriber), escape: '');
                }
            });

            fclose($out);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function destroy(Subscriber $subscriber): RedirectResponse
    {
        $this->authorize('manage newsletter');

        $subscriber->delete();

        Toast::success(__('Subscriber deleted.'));

        return back();
    }

    protected function query(?string $search, ?SubscriberStatus $status): Builder
    {
        return Subscriber::query()
            ->when($status, fn (Builder $query) => $query->where('status', $status->value))
            ->when($search, function (Builder $query) use ($search) {
                $like = '%'.$this->escapeLike(mb_strtolower($search)).'%';

                $query->where(fn (Builder $q) => $q->where('email', 'like', $like)->orWhereRaw('lower(name) like ?', [$like]));
            });
    }

    /**
     * @return array<string, int>
     */
    protected function counts(): array
    {
        $counts = Subscriber::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(SubscriberStatus::cases())
            ->mapWithKeys(fn (SubscriberStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)])
            ->all();
    }

    protected function search(Request $request): ?string
    {
        $search = $request->query('search');

        return is_string($search) && trim($search) !== '' ? mb_substr(trim($search), 0, 100) : null;
    }

    protected function status(Request $request): ?SubscriberStatus
    {
        $status = $request->query('status');

        return is_string($status) ? SubscriberStatus::tryFrom($status) : null;
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    protected function settingsUrl(): ?string
    {
        try {
            $addon = Addon::get(Settings::ADDON);

            return $addon && User::current()->can('editSettings', $addon)
                ? cp_route('addons.settings.edit', $addon->slug())
                : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
