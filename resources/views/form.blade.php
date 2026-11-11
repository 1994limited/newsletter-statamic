{{--
    The ready-made form's fields: {{ newsletter }}. Everything here is
    escaped already. Publish to edit: php artisan vendor:publish --tag=newsletter-views
--}}
@if($success)
    <p class="newsletter-success" role="status">{!! $success !!}</p>
@endif

@if($ask_name)
    <p class="newsletter-field">
        <label for="newsletter-name">{{ __('newsletter::messages.form.name') }}@unless($require_name) <span class="newsletter-optional">({{ __('newsletter::messages.form.optional') }})</span>@endunless</label>
        <input type="text" id="newsletter-name" name="name" value="{!! $name !!}" autocomplete="name" maxlength="255"@if($require_name) required @endif @if($error['name']) aria-invalid="true" aria-describedby="newsletter-name-error" @endif>
        @if($error['name'])<span class="newsletter-error" id="newsletter-name-error">{!! $error['name'] !!}</span>@endif
    </p>
@endif

<p class="newsletter-field">
    <label for="newsletter-email">{{ __('newsletter::messages.form.email') }}</label>
    <input type="email" id="newsletter-email" name="email" value="{!! $email !!}" autocomplete="email" maxlength="254" required @if($error['email']) aria-invalid="true" aria-describedby="newsletter-email-error" @endif>
    @if($error['email'])<span class="newsletter-error" id="newsletter-email-error">{!! $error['email'] !!}</span>@endif
</p>

@if($consent)
    <p class="newsletter-field newsletter-consent">
        <label>
            <input type="checkbox" name="consent" value="1" required @if($consent_checked) checked @endif @if($error['consent']) aria-invalid="true" aria-describedby="newsletter-consent-error" @endif>
            {!! $consent_text !!}
        </label>
        @if($error['consent'])<span class="newsletter-error" id="newsletter-consent-error">{!! $error['consent'] !!}</span>@endif
    </p>
@endif

<p><button type="submit">{!! $button !!}</button></p>
