<x-mail::message>
# Welcome to {{ $organization }}

Hi {{ $user->name }},

Your account is ready. You can sign in and start collaborating with the team.

**Username:** {{ $user->username }}  
**Email:** {{ $user->email }}

<x-mail::button :url="$loginUrl">
Go to dashboard
</x-mail::button>

If you did not expect this invite, you can ignore this email.

Thanks,<br>
{{ $organization }}
</x-mail::message>
