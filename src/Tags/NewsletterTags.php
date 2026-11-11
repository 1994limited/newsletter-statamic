<?php

namespace NineteenNinetyFour\Newsletter\Tags;

use Illuminate\Support\MessageBag;
use NineteenNinetyFour\Newsletter\Models\Subscriber;
use NineteenNinetyFour\Newsletter\Settings;
use Statamic\Tags\Concerns\RendersForms;
use Statamic\Tags\Tags;

/**
 * {{ newsletter }} and {{ newsletter:* }}
 *
 * The form works without JavaScript: it posts to /!/newsletter/subscribe
 * and comes back with a success message or errors. Everything a visitor
 * typed is escaped before it's handed to Antlers.
 *
 * The message and errors live in the session, so on a statically cached
 * page, put the form inside {{ nocache }}.
 */
class NewsletterTags extends Tags
{
    use RendersForms;

    protected static $handle = 'newsletter';

    /**
     * {{ newsletter }}: the ready-made form.
     *
     * {{ newsletter }} … {{ /newsletter }}: your own fields inside the
     * form, with {{ email }}, {{ name }}, {{ consent_text }},
     * {{ consent_checked }}, {{ success }}, {{ errors }} and
     * {{ error:email }} (and :name, :consent) to draw them with.
     */
    public function index(): string
    {
        return $this->form();
    }

    /**
     * {{ newsletter:form }}: the same as {{ newsletter }}.
     */
    public function form(): string
    {
        $data = $this->data();

        $html = $this->formOpen(url('!/newsletter/subscribe'), 'POST', ['redirect', 'error_redirect', 'button']);

        foreach (array_filter(['_redirect' => $this->params->get('redirect'), '_error_redirect' => $this->params->get('error_redirect')]) as $name => $value) {
            $html .= '<input type="hidden" name="'.e($name).'" value="'.e((string) $value).'">';
        }

        $html .= $this->isPair
            ? $this->parse($data)
            : view(config('newsletter.views.form', 'newsletter::form'), $data + ['button' => e((string) $this->params->get('button', __('newsletter::messages.form.button')))])->render();

        $html .= '<div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">'
            .'<label>'.e(__('newsletter::messages.form.honeypot')).' <input type="text" name="'.e((string) config('newsletter.honeypot', 'website')).'" value="" tabindex="-1" autocomplete="off"></label>'
            .'</div>';

        return $html.$this->formClose();
    }

    /**
     * {{ newsletter:success }}: the message after signing up (or after
     * confirming, when that lands on a page of your own).
     */
    public function success(): ?string
    {
        $message = session('newsletter_success');

        return is_string($message) ? e($message) : null;
    }

    /**
     * {{ newsletter:errors }} {{ value }} {{ /newsletter:errors }}
     */
    public function errors(): array
    {
        return collect($this->errorBag()?->all() ?? [])->map(fn ($message) => ['value' => e($message)])->all();
    }

    /**
     * {{ newsletter:count }}: how many confirmed subscribers there are.
     */
    public function count(): int
    {
        return Subscriber::query()->subscribed()->count();
    }

    /**
     * What the form's fields are drawn with, all escaped.
     *
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        $errors = $this->errorBag();
        $old = fn (string $key) => is_string($value = session()->getOldInput($key)) ? $value : '';

        return [
            'email' => e($old('email')),
            'name' => e($old('name')),
            'ask_name' => Settings::askName(),
            'require_name' => Settings::requireName(),
            'consent' => Settings::consent(),
            'consent_text' => e(Settings::text('consent_text')),
            'consent_checked' => (bool) session()->getOldInput('consent'),
            'success' => $this->success(),
            'errors' => collect($errors?->all() ?? [])->map(fn ($message) => e($message))->all(),
            'error' => [
                'email' => $errors?->first('email') ? e($errors->first('email')) : null,
                'name' => $errors?->first('name') ? e($errors->first('name')) : null,
                'consent' => $errors?->first('consent') ? e($errors->first('consent')) : null,
            ],
        ];
    }

    protected function errorBag(): ?MessageBag
    {
        $errors = session('errors');

        return $errors && method_exists($errors, 'getBag') && $errors->hasBag('newsletter') ? $errors->getBag('newsletter') : null;
    }
}
