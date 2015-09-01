@extends('mmic/intranet::layouts/default')

{{-- Page title --}}
@section('title')
{{{ trans('platform/users::auth/form.forgot-password.legend') }}} ::
@parent
@stop

{{-- Queue Assets --}}
{{ Asset::queue('platform-validate', 'platform/js/validate.js', 'jquery') }}

{{-- Inline Scripts --}}
@section('scripts')
@parent
@stop

{{-- Page content --}}
@section('page')

<div class="row">
	
	<div class="col-md-6 col-md-offset-3">
		
		<div class="panel panel-default">
			
			<div class="panel-body">
			
			<h1>Password reminders are disabled...</h1>
			
			<ul>
				<li>
					On this system, user passwords are controlled via Active Directory's
					LDAP service.
				</li>
			</ul>
			
			<p class="help-block">
				What's this mean for me? It means that if you can't remember your
				password, which is the same password that you use to log onto your
				work computer, you will need to contact I.T. support to reset your
				password for you.
			</p>
			
			</div>
			
		</div>
		
	</div>
	
</div>

@stop
