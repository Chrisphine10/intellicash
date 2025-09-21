@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-sm-3">
		<ul class="nav flex-column nav-tabs settings-tab mb-4" role="tablist">
			 <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#general"><i class="fas fa-cog"></i><span>{{ _lang('General Settings') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#currency_settings"><i class="fas fa-coins"></i><span>{{ _lang('Currency Settings') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#email"><i class="far fa-envelope"></i><span>{{ _lang('Email Settings') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#sms_gateway"><i class="ti-comment"></i>{{ _lang('SMS Gateways') }}</a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#recaptcha"><i class="far fa-check-circle"></i><span>{{ _lang('Google Recaptcha V3') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#logo"><i class="fas fa-tint"></i><span>{{ _lang('Logo and Favicon') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#cron_jobs"><i class="far fa-clock"></i><span>{{ _lang('Cron Jobs') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#cache"><i class="fas fa-server"></i><span>{{ _lang('Cache Control') }}</span></a></li>
		</ul>
	</div>

	@php $settings = \App\Models\Setting::all(); @endphp

	<div class="col-sm-9">
		<div class="tab-content">
			<div id="general" class="tab-pane active">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('General Settings') }}</span>
					</div>

					<div class="card-body">
						 <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('admin.settings.update_settings','store') }}" enctype="multipart/form-data">
							@csrf
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Company Name') }}</label>
										<input type="text" class="form-control" name="company_name" value="{{ get_setting($settings, 'company_name') }}" required>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Site Title') }}</label>
										<input type="text" class="form-control" name="site_title" value="{{ get_setting($settings, 'site_title') }}" required>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Phone') }}</label>
										<input type="text" class="form-control" name="phone" value="{{ get_setting($settings, 'phone') }}">
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Email') }}</label>
										<input type="email" class="form-control" name="email" value="{{ get_setting($settings, 'email') }}">
									</div>
								</div>

								<div class="col-md-6">
								  	<div class="form-group">
										<label class="control-label">{{ _lang('Backend Direction') }}</label>
										<select class="form-control" name="backend_direction" required>
											<option value="ltr" {{ get_setting($settings, 'backend_direction') == 'ltr' ? 'selected' : '' }}>{{ _lang('LTR') }}</option>
											<option value="rtl" {{ get_setting($settings, 'backend_direction') == 'rtl' ? 'selected' : '' }}>{{ _lang('RTL') }}</option>
										</select>
								  	</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Date Format') }}</label>
										<select class="form-control auto-select" name="date_format" data-selected="{{ get_setting($settings, 'date_format','Y-m-d') }}" required>
											<option value="Y-m-d">{{ date('Y-m-d') }}</option>
											<option value="d-m-Y">{{ date('d-m-Y') }}</option>
											<option value="d/m/Y">{{ date('d/m/Y') }}</option>
											<option value="m-d-Y">{{ date('m-d-Y') }}</option>
											<option value="m.d.Y">{{ date('m.d.Y') }}</option>
											<option value="m/d/Y">{{ date('m/d/Y') }}</option>
											<option value="d.m.Y">{{ date('d.m.Y') }}</option>
											<option value="d/M/Y">{{ date('d/M/Y') }}</option>
											<option value="d/M/Y">{{ date('M/d/Y') }}</option>
											<option value="d M, Y">{{ date('d M, Y') }}</option>
										</select>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Time Format') }}</label>
										<select class="form-control auto-select" name="time_format" data-selected="{{ get_setting($settings, 'time_format',24) }}" required>
											<option value="24">{{ _lang('24 Hours') }}</option>
											<option value="12">{{ _lang('12 Hours') }}</option>
										</select>
									</div>
								</div>

								<div class="col-md-6">
								  	<div class="form-group">
										<label class="control-label">{{ _lang('Email Verification') }}</label>
										<select class="form-control" name="email_verification" required>
											<option value="0" {{ get_setting($settings, 'email_verification') == '0' ? 'selected' : '' }}>{{ _lang('Disabled') }}</option>
											<option value="1" {{ get_setting($settings, 'email_verification') == '1' ? 'selected' : '' }}>{{ _lang('Enabled') }}</option>
										</select>
								  	</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Timezone') }}</label>
										<select class="form-control select2" name="timezone" required>
											<option value="">{{ _lang('-- Select One --') }}</option>
											{{ create_timezone_option(get_setting($settings, 'timezone')) }}
										</select>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Language') }}</label>
										<select class="form-control select2" name="language">
											<option value="">{{ _lang('-- Select One --') }}</option>
											{{ load_language( get_setting($settings, 'language') ) }}
										</select>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Member Signup') }}</label>
										<select class="form-control auto-select" name="member_signup" data-selected="{{ get_setting($settings, 'member_signup', 1) }}" required>
											<option value="1">{{ _lang('Yes') }}</option>
											<option value="0">{{ _lang('No') }}</option>
										</select>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Landing Page') }}</label>
										<select class="form-control auto-select" name="landing_page_status" data-selected="{{ get_setting($settings, 'landing_page_status', 1) }}" required>
											<option value="1">{{ _lang('Enabled') }}</option>
											<option value="0">{{ _lang('Disabled') }}</option>
										</select>
									</div>
								</div>

								<div class="col-md-12">
									<div class="form-group">
										<label class="control-label">{{ _lang('Address') }}</label>
										<textarea class="form-control" name="address">{{ get_setting($settings, 'address') }}</textarea>
									</div>
								</div>

								<div class="col-md-12 mt-2">
									<div class="form-group">
										<button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Save Settings') }}</button>
									</div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>

			<div id="currency_settings" class="tab-pane fade">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Currency Settings') }}</span>
					</div>

					<div class="card-body"> 
						<form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('admin.settings.update_settings','store') }}" enctype="multipart/form-data">
							@csrf
							<div class="row">
								
								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Currency') }}</label>
										<select class="form-control select2" name="currency" required>
											<option value="">{{ _lang('Select One') }}</option>
											{{ get_currency_list(get_setting($settings, 'currency')) }}
										</select>
									</div>
								</div>	
								
								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Currency Position') }}</label>						
										<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'currency_position','left') }}" name="currency_position" required>
											<option value="left">{{ _lang('Left') }}</option>
											<option value="right">{{ _lang('Right') }}</option>
										</select>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Thousand Seperator') }}</label>	
										<input type="text" class="form-control" name="thousand_sep" value="{{ get_setting($settings, 'thousand_sep',',') }}">
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Decimal Seperator') }}</label>	
										<input type="text" class="form-control" name="decimal_sep" value="{{ get_setting($settings, 'decimal_sep','.') }}">
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Decimal Places') }}</label>	
										<input type="text" class="form-control" name="decimal_places" value="{{ get_setting($settings, 'decimal_places',2) }}">
									</div>
								</div>

								<div class="col-md-12">
									<div class="form-group">
										<button type="submit" class="btn btn-primary">{{ _lang('Save Settings') }}</button>
									</div>
								</div>	
							</div>							
						</form>
					</div>
				</div>
			</div>

			<div id="email" class="tab-pane fade">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Email Settings') }}</span>
					</div>

					<div class="card-body">
						<form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('admin.settings.update_settings','store') }}" enctype="multipart/form-data">
							@csrf
							<div class="row">
								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('Mail Type') }}</label>
									<select class="form-control niceselect wide" name="mail_type" id="mail_type" required>
									  <option value="smtp" {{ get_setting($settings, 'mail_type')=="smtp" ? "selected" : "" }}>{{ _lang('SMTP') }}</option>
									  <option value="sendmail" {{ get_setting($settings, 'mail_type')=="sendmail" ? "selected" : "" }}>{{ _lang('Sendmail') }}</option>
									</select>
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('From Email') }}</label>
									<input type="text" class="form-control" name="from_email" value="{{ get_setting($settings, 'from_email') }}" required>
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('From Name') }}</label>
									<input type="text" class="form-control" name="from_name" value="{{ get_setting($settings, 'from_name') }}" required>
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('SMTP Host') }}</label>
									<input type="text" class="form-control smtp" name="smtp_host" value="{{ get_setting($settings, 'smtp_host') }}">
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('SMTP Port') }}</label>
									<input type="text" class="form-control smtp" name="smtp_port" value="{{ get_setting($settings, 'smtp_port') }}">
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('SMTP Username') }}</label>
									<input type="text" class="form-control smtp" autocomplete="off" name="smtp_username" value="{{ get_setting($settings, 'smtp_username') }}">
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('SMTP Password') }}</label>
									<input type="password" class="form-control smtp" autocomplete="off" name="smtp_password" value="{{ get_setting($settings, 'smtp_password') }}">
								  </div>
								</div>

								<div class="col-md-6">
								  <div class="form-group">
									<label class="control-label">{{ _lang('SMTP Encryption') }}</label>
									<select class="form-control smtp" name="smtp_encryption">
									   <option value="">{{ _lang('None') }}</option>
									   <option value="ssl" {{ get_setting($settings, 'smtp_encryption')=="ssl" ? "selected" : "" }}>{{ _lang('SSL') }}</option>
									   <option value="tls" {{ get_setting($settings, 'smtp_encryption')=="tls" ? "selected" : "" }}>{{ _lang('TLS') }}</option>
									</select>
								  </div>
								</div>

								<div class="col-md-12 mt-2">
								  	<div class="form-group">
										<button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Save Settings') }}</button>
								  	</div>
								</div>
							</div>
						</form>
					</div>
				</div>

				<div class="card mt-4">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Send Test Email') }}</span>
					</div>

					<div class="card-body">
						<form action="{{ route('admin.settings.send_test_email') }}" class="settings-submit params-panel" method="post">
							<div class="row">
								@csrf
								<div class="col-md-12">
									<div class="form-group">
										<label class="control-label">{{ _lang('Email To') }}</label>
										<input type="email" class="form-control" name="email_address" required>
									</div>
								</div>

								<div class="col-md-12">
									<div class="form-group">
										<label class="control-label">{{ _lang('Message') }}</label>
										<textarea class="form-control" name="message" required></textarea>
									</div>
								</div>

								<div class="col-md-12 mt-2">
									<div class="form-group">
										<button type="submit" class="btn btn-primary"><i class="far fa-paper-plane"></i>&nbsp;{{ _lang('Send Test Email') }}</button>
									</div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>

			<div id="sms_gateway" class="tab-pane fade">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('SMS Gateways') }}</span>
					</div>

					<div class="card-body">
						<div class="alert alert-primary">
							<i class="fas fa-info-circle"></i> {{ _lang('Please make sure to configure your SMS Gateway settings correctly before enabling sms gateway.') }}
						</div>

						<div class="accordion" id="sms_gateway">
							<div class="card">
								<div class="card-header params-panel" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
								  <strong>{{ _lang('Twilio') }}</strong>
								</div>

								<div id="collapseOne" class="collapse" aria-labelledby="headingOne" data-parent="#sms_gateway">
									<div class="card-body">
									   <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('admin.settings.update_settings','store') }}" enctype="multipart/form-data">
											@csrf
											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('SMS Gateway') }}</label>
												<div class="col-xl-9 col-lg-8">
													<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'sms_gateway', 'none') }}" name="sms_gateway" required>
														<option value="none">{{ _lang('None') }}</option>
														<option value="twilio">{{ _lang('Twilio') }}</option>
														<option value="textmagic">{{ _lang('Textmagic') }}</option>
														<option value="nexmo">{{ _lang('Nexmo') }}</option>
														<option value="infobip">{{ _lang('Infobip') }}</option>
														<option value="africas_talking">{{ _lang('Africa\'s Talking') }}</option>
													</select>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('Account SID') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="twilio_account_sid" value="{{ get_setting($settings, 'twilio_account_sid') }}" required>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('Auth Token') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="twilio_auth_token" value="{{ get_setting($settings, 'twilio_auth_token') }}" required>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('From Number') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="twilio_number" value="{{ get_setting($settings, 'twilio_number') }}" required>
												</div>
											</div>

											<div class="form-group row">
												<div class="col-xl-9 col-lg-8 offset-xl-3 offset-lg-4">
													<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Settings') }}</button>
												</div>
											</div>
										</form>
									</div>
								</div>
							</div>

							<div class="card mt-2">
								<div class="card-header params-panel" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="true" aria-controls="collapseTwo">
								  <strong>{{ _lang('Textmagic') }}</strong>
								</div>

								<div id="collapseTwo" class="collapse" aria-labelledby="headingTwo" data-parent="#sms_gateway">
									<div class="card-body">
									   <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('admin.settings.update_settings','store') }}" enctype="multipart/form-data">
											@csrf
											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('SMS Gateway') }}</label>
												<div class="col-xl-9 col-lg-8">
													<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'sms_gateway', 'none') }}" name="sms_gateway" required>
														<option value="none">{{ _lang('None') }}</option>
														<option value="twilio">{{ _lang('Twilio') }}</option>
														<option value="textmagic">{{ _lang('Textmagic') }}</option>
														<option value="nexmo">{{ _lang('Nexmo') }}</option>
														<option value="infobip">{{ _lang('Infobip') }}</option>
														<option value="africas_talking">{{ _lang('Africa\'s Talking') }}</option>
													</select>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('Username') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="textmagic_username" value="{{ get_setting($settings, 'textmagic_username') }}" required>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API V2 KEY') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="textmagic_api_key" value="{{ get_setting($settings, 'textmagic_api_key') }}" required>
												</div>
											</div>

											<div class="form-group row">
												<div class="col-xl-9 col-lg-8 offset-xl-3 offset-lg-4">
													<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Settings') }}</button>
												</div>
											</div>
										</form>
									</div>
								</div>
							</div> <!--End Textmagic -->

							<div class="card mt-2">
								<div class="card-header params-panel" data-toggle="collapse" data-target="#collapseThree" aria-expanded="true" aria-controls="collapseTwo">
								  <strong>{{ _lang('Nexmo') }}</strong>
								</div>

								<div id="collapseThree" class="collapse" aria-labelledby="headingThree" data-parent="#sms_gateway">
									<div class="card-body">
									   <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('admin.settings.update_settings','store') }}" enctype="multipart/form-data">
											@csrf
											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('SMS Gateway') }}</label>
												<div class="col-xl-9 col-lg-8">
													<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'sms_gateway', 'none') }}" name="sms_gateway" required>
														<option value="none">{{ _lang('None') }}</option>
														<option value="twilio">{{ _lang('Twilio') }}</option>
														<option value="textmagic">{{ _lang('Textmagic') }}</option>
														<option value="nexmo">{{ _lang('Nexmo') }}</option>
														<option value="infobip">{{ _lang('Infobip') }}</option>
														<option value="africas_talking">{{ _lang('Africa\'s Talking') }}</option>
													</select>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API KEY') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="nexmo_api_key" value="{{ get_setting($settings, 'nexmo_api_key') }}" required>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API Secret') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="nexmo_api_secret" value="{{ get_setting($settings, 'nexmo_api_secret') }}" required>
												</div>
											</div>

											<div class="form-group row">
												<div class="col-xl-9 col-lg-8 offset-xl-3 offset-lg-4">
													<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Settings') }}</button>
												</div>
											</div>
										</form>
									</div>
								</div>
							</div> <!--End Nexmo -->

							<div class="card mt-2">
								<div class="card-header params-panel" data-toggle="collapse" data-target="#collapseFour" aria-expanded="true" aria-controls="collapseTwo">
								  <strong>{{ _lang('Infobip') }}</strong>
								</div>

								<div id="collapseFour" class="collapse" aria-labelledby="headingFour" data-parent="#sms_gateway">
									<div class="card-body">
									   <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('admin.settings.update_settings','store') }}" enctype="multipart/form-data">
											@csrf
											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('SMS Gateway') }}</label>
												<div class="col-xl-9 col-lg-8">
													<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'sms_gateway', 'none') }}" name="sms_gateway" required>
														<option value="none">{{ _lang('None') }}</option>
														<option value="twilio">{{ _lang('Twilio') }}</option>
														<option value="textmagic">{{ _lang('Textmagic') }}</option>
														<option value="nexmo">{{ _lang('Nexmo') }}</option>
														<option value="infobip">{{ _lang('Infobip') }}</option>
														<option value="africas_talking">{{ _lang('Africa\'s Talking') }}</option>
													</select>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API KEY') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="infobip_api_key" value="{{ get_setting($settings, 'infobip_api_key') }}" required>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API BASE URL') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="infobip_api_base_url" value="{{ get_setting($settings, 'infobip_api_base_url') }}" required>
												</div>
											</div>

											<div class="form-group row">
												<div class="col-xl-9 col-lg-8 offset-xl-3 offset-lg-4">
													<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Settings') }}</button>
												</div>
											</div>
										</form>
									</div>
								</div>
							</div> <!--End Infobip -->

							<div class="card border mt-2">
								<div class="card-header params-panel" data-toggle="collapse" data-target="#collapseFive" aria-expanded="true" aria-controls="collapseFive">
								  <strong><i class="fas fa-long-arrow-alt-right"></i> {{ _lang('Africa\'s Talking') }}</strong>
								</div>

								<div id="collapseFive" class="collapse" aria-labelledby="headingFive" data-parent="#sms_gateway">
									<div class="card-body">
									   <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('admin.settings.update_settings','store') }}" enctype="multipart/form-data">
											@csrf
											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('SMS Gateway') }}</label>
												<div class="col-xl-9 col-lg-8">
													<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'sms_gateway', 'none') }}" name="sms_gateway" required>
														<option value="none">{{ _lang('None') }}</option>
														<option value="twilio">{{ _lang('Twilio') }}</option>
														<option value="textmagic">{{ _lang('Textmagic') }}</option>
														<option value="nexmo">{{ _lang('Nexmo') }}</option>
														<option value="infobip">{{ _lang('Infobip') }}</option>
														<option value="africas_talking">{{ _lang('Africa\'s Talking') }}</option>
													</select>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('Username') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="africas_talking_username" value="{{ get_setting($settings, 'africas_talking_username') }}" required>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API Key') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="africas_talking_api_key" value="{{ get_setting($settings, 'africas_talking_api_key') }}" required>
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('Sender ID') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="africas_talking_sender_id" value="{{ get_setting($settings, 'africas_talking_sender_id') }}" required>
												</div>
											</div>

											<div class="form-group row">
												<div class="col-xl-9 col-lg-8 offset-xl-3 offset-lg-4">
													<button type="submit" class="btn btn-primary"><i class="ti-check-box"></i>&nbsp;{{ _lang('Save Settings') }}</button>
												</div>
											</div>
										</form>
									</div>
								</div>
							</div> <!--End Africa's Talking -->

						</div>
					</div>
				</div>
			</div>


			<div id="recaptcha" class="tab-pane fade">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('GOOGLE RECAPTCHA V3') }}</span>
					</div>
					<div class="card-body">
						<form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('admin.settings.update_settings','store') }}">
							@csrf
							<div class="row">
								<div class="col-xl-12">
									<div class="form-group row">
										<label class="col-xl-4 col-form-label">{{ _lang('Enable Recaptcha v3') }}</label>
										<div class="col-xl-8">
											<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'enable_recaptcha', 0) }}" name="enable_recaptcha" required>
												<option value="0">{{ _lang('No') }}</option>
												<option value="1">{{ _lang('Yes') }}</option>
											</select>
										</div>
									</div>

									<div class="form-group row">
										<label class="col-xl-4 col-form-label">{{ _lang('RECAPTCHA SITE KEY') }}</label>
										<div class="col-xl-8">
											<input type="text" class="form-control" name="recaptcha_site_key" value="{{ get_setting($settings, 'recaptcha_site_key') }}">
										</div>
									</div>

									<div class="form-group row">
										<label class="col-xl-4 col-form-label">{{ _lang('RECAPTCHA SECRET KEY') }}</label>
										<div class="col-xl-8">
											<input type="text" class="form-control" name="recaptcha_secret_key" value="{{ get_setting($settings, 'recaptcha_secret_key') }}">
										</div>
									</div>

									<div class="form-group row mt-2">
										<div class="col-xl-8 offset-xl-4">
											<button type="submit" class="btn btn-primary">{{ _lang('Save Settings') }}</button>
										</div>
									</div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>

			<div id="logo" class="tab-pane fade">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Logo and Favicon') }}</span>
					</div>

					<div class="card-body">
						<div class="row">
							<div class="col-md-6">
								<form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('admin.settings.uplaod_logo') }}" enctype="multipart/form-data">
									@csrf
									<div class="row">
										<div class="col-md-12">
										  <div class="form-group">
											<label class="control-label">{{ _lang('Upload Logo') }}</label>
											<input type="file" class="form-control dropify" name="logo" data-max-file-size="2M" data-allowed-file-extensions="png jpg jpeg PNG JPG JPEG" data-show-remove="false" data-default-file="{{ get_logo() }}" required>
										  </div>
										</div>

										<br>
										<div class="col-md-12 mt-2">
										  <div class="form-group">
											<button type="submit" class="btn btn-primary btn-block">{{ _lang('Upload') }}</button>
										  </div>
										</div>
									</div>
								</form>
							</div>

							<div class="col-md-6">
								<form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('admin.settings.update_settings','store') }}" enctype="multipart/form-data">
									@csrf
									<div class="row">
										<div class="col-md-12">
										  <div class="form-group">
											<label class="control-label">{{ _lang('Upload Favicon') }} (PNG)</label>
											<input type="file" class="form-control dropify" name="favicon" data-max-file-size="1M" data-allowed-file-extensions="png" data-default-file="{{ get_favicon() }}" data-show-remove="false" required>
										  </div>
										</div>

										<div class="col-md-12 mt-2">
										  <div class="form-group">
											<button type="submit" class="btn btn-primary btn-block">{{ _lang('Upload') }}</button>
										  </div>
										</div>
									</div>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div><!--End Logo Tab-->

			<div id="cron_jobs" class="tab-pane fade">
				<div class="card">
					<div class="card-header d-flex align-items-center justify-content-between">
						<span class="panel-title">{{ _lang('Cron Jobs') }}</span>
						<span>{{ get_option('cornjob_runs_at') != null ? _lang('Last Runs At').' ('.date(get_date_format().' '.get_time_format(), strtotime(get_option('cornjob_runs_at'))).' UTC)' : '' }}</span>
					</div>

					<div class="card-body">
						<div class="alert alert-warning">
							<span><i class="ti-info-alt"></i>&nbsp;{{ _lang('Run Cronjobs at least every').' 5 '._lang('minutes') }}</span>
						</div>
						<div class="row">
							<div class="col-md-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Schedule Task Command') }}</label>
									<div class="border bg-light p-2 rounded">cd /<span class="text-danger">your-project-path</span> && php artisan schedule:run >> /dev/null 2>&1</div>
								</div>
							</div>

							<div class="col-md-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Cronjobs Command example 1 for cPanel') }}</label>
									<div class="border bg-light p-2 rounded">{{ '/usr/local/bin/php ' . base_path() . '/artisan schedule:run >> /dev/null 2>&1' }}</div>
								</div>
							</div>

							<div class="col-md-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Cronjobs Command example 2 for cPanel') }}</label>
									<div class="border bg-light p-2 rounded">{{ 'cd ' . base_path() .  ' && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1' }}</div>
								</div>
							</div>

							<div class="col-md-12">
								<div class="form-group">
									<label class="control-label">{{ _lang('Schedule Task Command example for Plesk') }}</label>
									<div class="border bg-light p-2 rounded">{{ 'cd ' . base_path() .  ' && /opt/plesk/php/'. substr(phpversion(), 0, 3) .'/bin/php artisan schedule:run >> /dev/null 2>&1' }}</div>
								</div>
							</div>
						</div>
				   </div>
				</div>
			</div>

			<div id="cache" class="tab-pane fade">
				<div class="card">
					<div class="card-header">
						<span class="panel-title">{{ _lang('Cache Control') }}</span>
					</div>

					<div class="card-body">
						<form method="post" class="params-panel" autocomplete="off" action="{{ route('admin.settings.remove_cache') }}">
							@csrf
							<div class="row">
								<div class="col-md-12">
									<div class="checkbox">
										<div class="custom-control custom-checkbox">
											<input type="checkbox" class="custom-control-input" name="cache[view_cache]" value="view_cache" id="view_cache">
											<label class="custom-control-label" for="view_cache">{{ _lang('View Cache') }}</label>
										</div>
									</div>
								</div>

								<div class="col-md-12">
									<div class="checkbox">
										<div class="custom-control custom-checkbox">
											<input type="checkbox" class="custom-control-input" name="cache[application_cache]" value="application_cache" id="application_cache">
											<label class="custom-control-label" for="application_cache">{{ _lang('Application Cache') }}</label>
										</div>
									</div>
								</div>

								<div class="col-md-12 mt-4">
								  <div class="form-group">
									<button type="submit" class="btn btn-primary">{{ _lang('Remove Cache') }}</button>
								  </div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div><!--End Cache Tab-->
		</div>
	</div>
</div>
@endsection
