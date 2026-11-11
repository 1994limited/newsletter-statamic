{{ __('newsletter::messages.notify.body', ['email' => $subscriber->email]) }}@if($subscriber->name) ({{ $subscriber->name }})@endif


{{ __('newsletter::messages.notify.count', ['count' => $count]) }} {!! $listUrl !!}
