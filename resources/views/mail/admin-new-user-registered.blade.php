<x-mail::message>
# New team registration

A new user just joined **{{ $organization }}** via an invite link.

- **Name:** {{ $newUser->name }}
- **Username:** {{ $newUser->username }}
- **Email:** {{ $newUser->email }}
@if ($roleName)
- **Role:** {{ $roleName }}
@endif

<x-mail::button :url="$teamUrl">
Open team settings
</x-mail::button>

Thanks,<br>
{{ $organization }}
</x-mail::message>
