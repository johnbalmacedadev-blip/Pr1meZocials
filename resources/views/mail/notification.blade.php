<x-mail::message>
# {{ $heading }}

Hi {{ $recipientName }},

{{ $body }}

<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>

You can change which emails you receive under Settings → Notifications or on your profile.

Thanks,<br>
{{ $organization }}
</x-mail::message>
