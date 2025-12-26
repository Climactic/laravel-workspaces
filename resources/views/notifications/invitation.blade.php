@component('mail::message')
# You've Been Invited!

{{ $inviterName }} has invited you to join **{{ $workspaceName }}** as a **{{ $roleName }}**.

@component('mail::button', ['url' => $acceptUrl])
Accept Invitation
@endcomponent

This invitation will expire on {{ $expiresAt }}.

If you did not expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
