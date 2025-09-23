@extends('layouts.app')

@section('content')
<div class="row">
	<div class="{{ $alert_col }}">
		<ul class="nav nav-tabs business-settings-tabs" role="tablist">
			 <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#general_settings"><i class="fas fa-cog mr-2"></i><span>{{ _lang('General Settings') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#system_settings"><i class="fas fa-tools mr-2"></i><span>{{ _lang('System Settings') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#currency_settings"><i class="fas fa-pound-sign mr-2"></i><span>{{ _lang('Currency Settings') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#transaction_fee"><i class="fas fa-dollar-sign mr-2"></i><span>{{ _lang('Transaction Fee') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#email"><i class="fas fa-at mr-2"></i><span>{{ _lang('Email Settings') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#sms_gateway"><i class="fas fa-sms mr-2"></i><span>{{ _lang('SMS Settings') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#logo"><i class="fas fa-camera mr-2"></i><span>{{ _lang('Logo') }}</span></a></li>
			 <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#pwa_settings"><i class="fas fa-mobile-alt mr-2"></i><span>{{ _lang('PWA Settings') }}</span></a></li>
		</ul>

		<div class="tab-content settings-tab-content">
			<div id="general_settings" class="tab-pane active">
				<div class="card">
                    <div class="card-header">
                        <span>{{ _lang('General Settings') }}</span>
                    </div>
					<div class="card-body">
						<form action="{{ route('settings.store_general_settings') }}" class="settings-submit" autocomplete="off" method="post" enctype="multipart/form-data">
							@csrf
                            <div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Business Name') }}</label>	
								<div class="col-xl-9">
									<input type="text" class="form-control" name="business_name" value="{{ get_setting($settings, 'business_name', request()->tenant->name) }}" required>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Email') }}</label>	
								<div class="col-xl-9">
									<input type="email" class="form-control" name="email" value="{{ get_setting($settings, 'email') }}">
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Phone') }}</label>	
								<div class="col-xl-9">
									<input type="text" class="form-control" name="phone" value="{{ get_setting($settings, 'phone') }}">
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Timezone') }}</label>
								<div class="col-xl-9">
									<select class="form-control select2 auto-select" data-selected="{{ get_setting($settings, 'timezone','') }}" name="timezone" required>
										<option value="">{{ _lang('Select One') }}</option>
										{{ create_timezone_option() }}
									</select>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Language') }}</label>
								<div class="col-xl-9">
									<select class="form-control select2 auto-select" name="language" data-selected="{{ get_setting($settings, 'language','') }}" required>
										<option value="">{{ _lang('Select One') }}</option>
										{{ load_language() }}
									</select>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Address') }}</label>	
								<div class="col-xl-9">
									<textarea class="form-control" name="address">{{ get_setting($settings, 'address') }}</textarea>
								</div>
							</div>

							<div class="form-group row mt-2">
								<div class="col-xl-9 offset-lg-3">
									<button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Save Changes') }}</button>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>

			<div id="system_settings" class="tab-pane">
				<div class="card">
                    <div class="card-header">
                        <span>{{ _lang('System Settings') }}</span>
                    </div>
					<div class="card-body">
						<form action="{{ route('settings.store_general_settings') }}" class="settings-submit" autocomplete="off" method="post" enctype="multipart/form-data">
							@csrf

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Starting Member No') }}</label>	
								<div class="col-xl-9">
									<input type="text" class="form-control" name="starting_member_no" value="{{ get_setting($settings, 'starting_member_no') }}">
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Members Sign Up Form') }}</label>
								<div class="col-xl-9">
									<select class="form-control auto-select" name="members_sign_up" data-selected="{{ get_setting($settings, 'members_sign_up', 0) }}" required>
										<option value="0">{{ _lang('Disabled') }}</option>
										<option value="1">{{ _lang('Active') }}</option>
									</select>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Default Branch Name') }}</label>	
								<div class="col-xl-9">
									<input type="text" class="form-control" name="default_branch_name" value="{{ get_setting($settings, 'default_branch_name', 'Main Branch') }}">
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Backend Direction') }}</label>
								<div class="col-xl-9">
									<select class="form-control auto-select" name="backend_direction" data-selected="{{ get_setting($settings, 'backend_direction', 'ltr') }}" required>
										<option value="ltr">{{ _lang('LTR') }}</option>
										<option value="rtl">{{ _lang('RTL') }}</option>
									</select>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Date Format') }}</label>
								<div class="col-xl-9">
									<select class="form-control auto-select" name="date_format" data-selected="{{ get_setting($settings, 'date_format', 'Y-m-d') }}" required>
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

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Time Format') }}</label>
								<div class="col-xl-9">
									<select class="form-control auto-select" name="time_format" data-selected="{{ get_setting($settings, 'time_format', 24) }}" required>
										<option value="24">{{ _lang('24 Hours') }}</option>
										<option value="12">{{ _lang('12 Hours') }}</option>
									</select>
								</div>
							</div>

							<div class="form-group row mt-2">
								<div class="col-xl-9 offset-lg-3">
									<button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Save Changes') }}</button>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>

			<div id="currency_settings" class="tab-pane">
				<div class="card">
                    <div class="card-header">
                        <span>{{ _lang('Currency Settings') }}</span>
                    </div>
					<div class="card-body">
						<form method="post" class="settings-submit" autocomplete="off" action="{{ route('settings.store_currency_settings') }}" enctype="multipart/form-data">
							@csrf	                   
                            
							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Currency Position') }}</label>						
								<div class="col-xl-9">
									<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'currency_position', 'left') }}" name="currency_position" required>
										<option value="left">{{ _lang('Left') }}</option>
										<option value="right">{{ _lang('Right') }}</option>
									</select>
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Thousand Seperator') }}</label>	
								<div class="col-xl-9">
									<input type="text" class="form-control" name="thousand_sep" value="{{ get_setting($settings, 'thousand_sep', ',') }}">
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Decimal Seperator') }}</label>	
								<div class="col-xl-9">
									<input type="text" class="form-control" name="decimal_sep" value="{{ get_setting($settings, 'decimal_sep', '.') }}">
								</div>
							</div>

							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Decimal Places') }}</label>	
								<div class="col-xl-9">
									<input type="text" class="form-control" name="decimal_places" value="{{ get_setting($settings, 'decimal_places', 2) }}" required>
								</div>
							</div>

							<div class="form-group row">
								<div class="col-xl-9 offset-xl-3">
									<button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Save Settings') }}</button>
								</div>
							</div>								
						</form>
					</div>
				</div>
			</div>

            <div id="transaction_fee" class="tab-pane">
                <div class="card">
                    <div class="card-header">
                        <span>{{ _lang('Transaction Fee') }}</span>
                    </div>
                    <div class="card-body">
                        <form method="post" class="settings-submit" autocomplete="off" action="{{ route('settings.store_general_settings') }}">
                            @csrf
                            <div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Own Account Transfer Fee Type') }}</label>
										<select class="form-control auto-select" data-selected="percentage" name="own_account_transfer_fee_type">
											<option value="percentage">{{ _lang('Percentage') }}</option>
											<option value="fixed">{{ _lang('Fixed') }}</option>
										</select>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Own Account Transfer Fee') }}</label>
										<div class="input-group">
											<div class="input-group-prepend">
												<span class="input-group-text">{{ get_base_currency() }} / %</span>
											</div>
											<input type="text" class="form-control float-field" name="own_account_transfer_fee" value="1" required="">
										</div>               
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Other Account Transfer Fee Type') }}</label>
										<select class="form-control auto-select" data-selected="percentage" name="other_account_transfer_fee_type">
											<option value="percentage">{{ _lang('Percentage') }}</option>
											<option value="fixed">{{ _lang('Fixed') }}</option>
										</select>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Other Account Transfer Fee') }}</label>
										<div class="input-group">
											<div class="input-group-prepend">
												<span class="input-group-text">{{ get_base_currency() }} / %</span>
											</div>
											<input type="text" class="form-control float-field" name="other_account_transfer_fee" value="1" required="">
										</div>               
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


			<div id="email" class="tab-pane">
				<div class="row">
					<div class="col-lg-8 mb-md-4">
						<div class="card">
							<div class="card-header">
								<span>{{ _lang('Email Configuration') }}</span>
							</div>
							<div class="card-body">
								<form method="post" class="settings-submit" autocomplete="off" action="{{ route('settings.store_email_settings') }}" enctype="multipart/form-data">
									@csrf
									<div class="form-group row">
										<label class="col-xl-3 col-form-label">{{ _lang('Mail Type') }}</label>
										<div class="col-xl-9">
											<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'mail_type', '') }}" name="mail_type" id="mail_type">
												<option value="">{{ _lang('None') }}</option>
												<option value="smtp">{{ _lang('SMTP') }}</option>
												<option value="sendmail">{{ _lang('Sendmail') }}</option>
											</select>
										</div>
									</div>

									<div class="form-group row">
										<label class="col-xl-3 col-form-label">{{ _lang('From Email') }}</label>
										<div class="col-xl-9">
											<input type="text" class="form-control" name="from_email" value="{{ get_setting($settings, 'from_email', '') }}">
										</div>
									</div>

									<div class="form-group row">
										<label class="col-xl-3 col-form-label">{{ _lang('From Name') }}</label>
										<div class="col-xl-9">
											<input type="text" class="form-control" name="from_name" value="{{ get_setting($settings, 'from_name', '') }}">
										</div>
									</div>

									<div class="form-group row">
										<label class="col-xl-3 col-form-label">{{ _lang('SMTP Host') }}</label>
										<div class="col-xl-9">
											<input type="text" class="form-control smtp" name="smtp_host" value="{{ get_setting($settings, 'smtp_host', '') }}">
										</div>
									</div>

									<div class="form-group row">
										<label class="col-xl-3 col-form-label">{{ _lang('SMTP Port') }}</label>
										<div class="col-xl-9">
											<input type="text" class="form-control smtp" name="smtp_port" value="{{ get_setting($settings, 'smtp_port', '') }}">
										</div>
									</div>

									<div class="form-group row">
										<label class="col-xl-3 col-form-label">{{ _lang('SMTP Username') }}</label>
										<div class="col-xl-9">
											<input type="text" class="form-control smtp" autocomplete="off" name="smtp_username" value="{{ get_setting($settings, 'smtp_username', '') }}">
										</div>
									</div>

									<div class="form-group row">
										<label class="col-xl-3 col-form-label">{{ _lang('SMTP Password') }}</label>
										<div class="col-xl-9">
											<input type="password" class="form-control smtp" autocomplete="off" name="smtp_password" value="{{ get_setting($settings, 'smtp_password', '') }}">
										</div>
									</div>

									<div class="form-group row">
										<label class="col-xl-3 col-form-label">{{ _lang('SMTP Encryption') }}</label>
										<div class="col-xl-9">
											<select class="form-control smtp auto-select" data-selected="{{ get_setting($settings, 'smtp_encryption', '') }}" name="smtp_encryption">
												<option value="">{{ _lang('None') }}</option>
												<option value="ssl">{{ _lang('SSL') }}</option>
												<option value="tls">{{ _lang('TLS') }}</option>
											</select>
										</div>
									</div>

									<div class="form-group row">
										<div class="col-xl-9 offset-xl-3">
											<button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Save Settings') }}</button>
										</div>
									</div>	
								</form>
							</div>
						</div>
					</div>

					<div class="col-lg-4">
						<div class="card">
							<div class="card-header">
								<span>{{ _lang('Send Test Email') }}</span>
							</div>
							<div class="card-body">
								<form method="post" class="settings-submit" autocomplete="off" action="{{ route('settings.send_test_email') }}">
									@csrf
									<div class="form-group">
										<label class="control-label">{{ _lang('Recipient Email') }}</label>
										<input type="email" class="form-control" name="recipient_email">
									</div>

									<div class="form-group">
										<label class="control-label">{{ _lang('Message') }}</label>
										<textarea class="form-control" name="message"></textarea>
									</div>

									<div class="form-group">
										<button type="submit" class="btn btn-primary btn-block"><i class="far fa-paper-plane mr-2"></i>{{ _lang('Send Test Email') }}</button>
									</div>	
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>

			<div id="sms_gateway" class="tab-pane fade">

				<div class="card">
					<div class="card-header">
						<span>{{ _lang('SMS Gateways') }}</span>
					</div>

					<div class="card-body">
						<div class="alert alert-primary">
							<i class="fas fa-info-circle"></i> {{ _lang('Please make sure to configure your SMS Gateway settings correctly. If you do not configure your SMS Gateway settings correctly, you will not be able to send SMS.') }}
						</div>
						<div class="accordion" id="sms_gateway">
							<div class="card border">
								<div class="card-header params-panel" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
								  <strong><i class="fas fa-long-arrow-alt-right"></i> {{ _lang('Twilio') }}</strong>
								</div>

								<div id="collapseOne" class="collapse" aria-labelledby="headingOne" data-parent="#sms_gateway">
									<div class="card-body">
									   <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.store_general_settings') }}">
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
													<input type="text" class="form-control" name="twilio_account_sid" value="{{ get_setting($settings, 'twilio_account_sid') }}">
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('Auth Token') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="twilio_auth_token" value="{{ get_setting($settings, 'twilio_auth_token') }}">
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('From Number') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="twilio_number" value="{{ get_setting($settings, 'twilio_number') }}">
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

							<div class="card border mt-2">
								<div class="card-header params-panel" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="true" aria-controls="collapseTwo">
								  <strong><i class="fas fa-long-arrow-alt-right"></i> {{ _lang('Textmagic') }}</strong>
								</div>

								<div id="collapseTwo" class="collapse" aria-labelledby="headingTwo" data-parent="#sms_gateway">
									<div class="card-body">
									   <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.store_general_settings') }}">
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
													<input type="text" class="form-control" name="textmagic_username" value="{{ get_setting($settings, 'textmagic_username') }}">
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API V2 KEY') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="textmagic_api_key" value="{{ get_setting($settings, 'textmagic_api_key') }}">
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

							<div class="card border mt-2">
								<div class="card-header params-panel" data-toggle="collapse" data-target="#collapseThree" aria-expanded="true" aria-controls="collapseTwo">
								  <strong><i class="fas fa-long-arrow-alt-right"></i> {{ _lang('Nexmo') }}</strong>
								</div>

								<div id="collapseThree" class="collapse" aria-labelledby="headingThree" data-parent="#sms_gateway">
									<div class="card-body">
									   <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.store_general_settings') }}">
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
													<input type="text" class="form-control" name="nexmo_api_key" value="{{ get_setting($settings, 'nexmo_api_key') }}">
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API Secret') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="nexmo_api_secret" value="{{ get_setting($settings, 'nexmo_api_secret') }}">
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

							<div class="card border mt-2">
								<div class="card-header params-panel" data-toggle="collapse" data-target="#collapseFour" aria-expanded="true" aria-controls="collapseTwo">
								  <strong><i class="fas fa-long-arrow-alt-right"></i> {{ _lang('Infobip') }}</strong>
								</div>

								<div id="collapseFour" class="collapse" aria-labelledby="headingFour" data-parent="#sms_gateway">
									<div class="card-body">
									   <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.store_general_settings') }}">
											@csrf
											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('SMS Gateway') }}</label>
												<div class="col-xl-9 col-lg-8">
													<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'sms_gateway', 'none') }}" name="sms_gateway">
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
													<input type="text" class="form-control" name="infobip_api_key" value="{{ get_setting($settings, 'infobip_api_key') }}">
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API BASE URL') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="infobip_api_base_url" value="{{ get_setting($settings, 'infobip_api_base_url') }}">
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
									   <form method="post" class="settings-submit params-panel" autocomplete="off" action="{{ route('settings.store_general_settings') }}">
											@csrf
											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('SMS Gateway') }}</label>
												<div class="col-xl-9 col-lg-8">
													<select class="form-control auto-select" data-selected="{{ get_setting($settings, 'sms_gateway', 'none') }}" name="sms_gateway">
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
													<input type="text" class="form-control" name="africas_talking_username" value="{{ get_setting($settings, 'africas_talking_username') }}">
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('API Key') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="africas_talking_api_key" value="{{ get_setting($settings, 'africas_talking_api_key') }}">
												</div>
											</div>

											<div class="form-group row">
												<label class="col-xl-3 col-lg-4 col-form-label">{{ _lang('Sender ID') }}</label>
												<div class="col-xl-9 col-lg-8">
													<input type="text" class="form-control" name="africas_talking_sender_id" value="{{ get_setting($settings, 'africas_talking_sender_id') }}">
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

			<div id="logo" class="tab-pane">
				<div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span>{{ _lang('Business Logo') }}</span>

						@if(get_setting($settings, 'logo') != '')
						<form action="{{ route('settings.store_general_settings') }}" method="post" class="d-inline-block">
							@csrf
							<input type="hidden" name="logo" value="">
							<button type="submit" class="btn btn-danger btn-xs btn-remove"><i class="far fa-trash-alt mr-1"></i>{{ _lang('Remove Logo') }}</button>
						</form>
						@endif
                    </div>
					<div class="card-body">
						<form method="post" class="settings-submit" autocomplete="off" action="{{ route('settings.upload_logo') }}" enctype="multipart/form-data">
							@csrf
							<div class="form-group row">
								<label class="col-xl-3 col-form-label">{{ _lang('Business Logo') }}</label>
								<div class="col-xl-9">
									<input type="file" class="dropify" name="logo" data-show-remove="false" data-default-file="{{ get_setting($settings, 'logo') != '' ? asset('public/uploads/media/'. get_setting($settings, 'logo')) : '' }}" required>
									<small>{{ _lang('Allowed File Extensions: jpg, png') }}</small>
								</div>
							</div>

							<div class="form-group row">
								<div class="col-xl-9 offset-xl-3">
									<button type="submit" class="btn btn-primary"><i class="ti-check-box mr-2"></i>{{ _lang('Upload') }}</button>
								</div>
							</div>	
						</form>
					</div>
				</div>
			</div>

			<div id="pwa_settings" class="tab-pane">
				<div class="card">
					<div class="card-header">
						<span>{{ _lang('Progressive Web App (PWA) Settings') }}</span>
					</div>
					<div class="card-body">
						<form method="post" class="settings-submit" autocomplete="off" action="{{ route('settings.store_general_settings') }}" enctype="multipart/form-data">
							@csrf
							
							<div class="row">
								<div class="col-md-12">
									<div class="form-group">
										<div class="custom-control custom-switch">
											<input type="checkbox" class="custom-control-input" id="pwa_enabled" name="pwa_enabled" value="1" {{ get_setting($settings, 'pwa_enabled') == '1' ? 'checked' : '' }}>
											<label class="custom-control-label" for="pwa_enabled">{{ _lang('Enable PWA for Members') }}</label>
										</div>
										<small class="form-text text-muted">{{ _lang('Enable Progressive Web App functionality for member mobile access') }}</small>
									</div>
								</div>
							</div>

							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('App Name') }}</label>
										<input type="text" class="form-control" name="pwa_app_name" value="{{ get_setting($settings, 'pwa_app_name', get_setting($settings, 'business_name', 'IntelliCash')) }}">
										<small class="form-text text-muted">{{ _lang('Full name of your PWA app') }}</small>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Short Name') }}</label>
										<input type="text" class="form-control" name="pwa_short_name" value="{{ get_setting($settings, 'pwa_short_name', get_setting($settings, 'business_name', 'IntelliCash')) }}">
										<small class="form-text text-muted">{{ _lang('Short name displayed on home screen') }}</small>
									</div>
								</div>
							</div>

							<div class="row">
								<div class="col-md-12">
									<div class="form-group">
										<label class="control-label">{{ _lang('App Description') }}</label>
										<textarea class="form-control" name="pwa_description" rows="3">{{ get_setting($settings, 'pwa_description', 'Mobile banking app for managing your finances') }}</textarea>
										<small class="form-text text-muted">{{ _lang('Description of your PWA app') }}</small>
									</div>
								</div>
							</div>

							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Theme Color') }}</label>
										<input type="color" class="form-control" name="pwa_theme_color" value="{{ get_setting($settings, 'pwa_theme_color', get_setting($settings, 'primary_color', '#007bff')) }}">
										<small class="form-text text-muted">{{ _lang('Theme color for the PWA') }}</small>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Background Color') }}</label>
										<input type="color" class="form-control" name="pwa_background_color" value="{{ get_setting($settings, 'pwa_background_color', '#ffffff') }}">
										<small class="form-text text-muted">{{ _lang('Background color for the PWA') }}</small>
									</div>
								</div>
							</div>

							<div class="row">
								<div class="col-md-12">
									<h6 class="mt-4 mb-3">{{ _lang('Mobile App Shortcuts') }}</h6>
								</div>
							</div>

							<div class="row">
								<div class="col-md-4">
									<div class="form-group">
										<div class="custom-control custom-switch">
											<input type="checkbox" class="custom-control-input" id="pwa_shortcut_dashboard" name="pwa_shortcut_dashboard" value="1" {{ get_setting($settings, 'pwa_shortcut_dashboard') == '1' ? 'checked' : '' }}>
											<label class="custom-control-label" for="pwa_shortcut_dashboard">{{ _lang('Dashboard Shortcut') }}</label>
										</div>
									</div>
								</div>

								<div class="col-md-4">
									<div class="form-group">
										<div class="custom-control custom-switch">
											<input type="checkbox" class="custom-control-input" id="pwa_shortcut_transactions" name="pwa_shortcut_transactions" value="1" {{ get_setting($settings, 'pwa_shortcut_transactions') == '1' ? 'checked' : '' }}>
											<label class="custom-control-label" for="pwa_shortcut_transactions">{{ _lang('Transactions Shortcut') }}</label>
										</div>
									</div>
								</div>

								<div class="col-md-4">
									<div class="form-group">
										<div class="custom-control custom-switch">
											<input type="checkbox" class="custom-control-input" id="pwa_shortcut_loans" name="pwa_shortcut_loans" value="1" {{ get_setting($settings, 'pwa_shortcut_loans') == '1' ? 'checked' : '' }}>
											<label class="custom-control-label" for="pwa_shortcut_loans">{{ _lang('Loans Shortcut') }}</label>
										</div>
									</div>
								</div>
							</div>

							<div class="row">
								<div class="col-md-4">
									<div class="form-group">
										<div class="custom-control custom-switch">
											<input type="checkbox" class="custom-control-input" id="pwa_shortcut_deposit" name="pwa_shortcut_deposit" value="1" {{ get_setting($settings, 'pwa_shortcut_deposit') == '1' ? 'checked' : '' }}>
											<label class="custom-control-label" for="pwa_shortcut_deposit">{{ _lang('Deposit Shortcut') }}</label>
										</div>
									</div>
								</div>

								<div class="col-md-4">
									<div class="form-group">
										<div class="custom-control custom-switch">
											<input type="checkbox" class="custom-control-input" id="pwa_shortcut_profile" name="pwa_shortcut_profile" value="1" {{ get_setting($settings, 'pwa_shortcut_profile') == '1' ? 'checked' : '' }}>
											<label class="custom-control-label" for="pwa_shortcut_profile">{{ _lang('Profile Shortcut') }}</label>
										</div>
									</div>
								</div>
							</div>

							<div class="row">
								<div class="col-md-12">
									<div class="form-group">
										<button type="submit" class="btn btn-primary">{{ _lang('Save Settings') }}</button>
										<a href="{{ route('pwa.install-prompt') }}" target="_blank" class="btn btn-info ml-2">{{ _lang('Test PWA') }}</a>
									</div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>

							<div class="row">
								<div class="col-md-12">
									<div class="form-group">
										<label class="control-label">{{ _lang('App Description') }}</label>
										<textarea class="form-control" name="pwa_description" rows="3">{{ get_setting($settings, 'pwa_description', 'Progressive Web App for IntelliCash - Manage your finances efficiently') }}</textarea>
										<small class="form-text text-muted">{{ _lang('Description of your PWA app') }}</small>
									</div>
								</div>
							</div>

							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Theme Color') }}</label>
										<input type="color" class="form-control" name="pwa_theme_color" value="{{ get_setting($settings, 'pwa_theme_color', '#007bff') }}">
										<small class="form-text text-muted">{{ _lang('Theme color for the PWA') }}</small>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Background Color') }}</label>
										<input type="color" class="form-control" name="pwa_background_color" value="{{ get_setting($settings, 'pwa_background_color', '#ffffff') }}">
										<small class="form-text text-muted">{{ _lang('Background color for the PWA') }}</small>
									</div>
								</div>
							</div>

							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Display Mode') }}</label>
										<select class="form-control auto-select" name="pwa_display_mode" data-selected="{{ get_setting($settings, 'pwa_display_mode', 'standalone') }}">
											<option value="standalone">{{ _lang('Standalone') }}</option>
											<option value="fullscreen">{{ _lang('Fullscreen') }}</option>
											<option value="minimal-ui">{{ _lang('Minimal UI') }}</option>
											<option value="browser">{{ _lang('Browser') }}</option>
										</select>
										<small class="form-text text-muted">{{ _lang('How the app is displayed when launched') }}</small>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Orientation') }}</label>
										<select class="form-control auto-select" name="pwa_orientation" data-selected="{{ get_setting($settings, 'pwa_orientation', 'portrait-primary') }}">
											<option value="portrait-primary">{{ _lang('Portrait Primary') }}</option>
											<option value="landscape-primary">{{ _lang('Landscape Primary') }}</option>
											<option value="any">{{ _lang('Any') }}</option>
										</select>
										<small class="form-text text-muted">{{ _lang('Preferred orientation for the app') }}</small>
									</div>
								</div>
							</div>

							<div class="row">
								<div class="col-md-12">
									<h6 class="mt-4 mb-3">{{ _lang('App Icons') }}</h6>
								</div>
							</div>

							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('App Icon (192x192)') }}</label>
										<input type="file" class="dropify" name="pwa_icon_192" data-default-file="{{ get_setting($settings, 'pwa_icon_192') ? asset('public/uploads/media/'.get_setting($settings, 'pwa_icon_192')) : '' }}">
										<small class="form-text text-muted">{{ _lang('192x192 PNG icon for Android devices') }}</small>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('App Icon (512x512)') }}</label>
										<input type="file" class="dropify" name="pwa_icon_512" data-default-file="{{ get_setting($settings, 'pwa_icon_512') ? asset('public/uploads/media/'.get_setting($settings, 'pwa_icon_512')) : '' }}">
										<small class="form-text text-muted">{{ _lang('512x512 PNG icon for high-resolution displays') }}</small>
									</div>
								</div>
							</div>

							<div class="row">
								<div class="col-md-12">
									<h6 class="mt-4 mb-3">{{ _lang('Shortcuts') }}</h6>
								</div>
							</div>

							<div class="row">
								<div class="col-md-4">
									<div class="form-group">
										<div class="custom-control custom-switch">
											<input type="checkbox" class="custom-control-input" id="pwa_shortcut_dashboard" name="pwa_shortcut_dashboard" value="1" {{ get_setting($settings, 'pwa_shortcut_dashboard') == '1' ? 'checked' : '' }}>
											<label class="custom-control-label" for="pwa_shortcut_dashboard">{{ _lang('Dashboard Shortcut') }}</label>
										</div>
									</div>
								</div>

								<div class="col-md-4">
									<div class="form-group">
										<div class="custom-control custom-switch">
											<input type="checkbox" class="custom-control-input" id="pwa_shortcut_transactions" name="pwa_shortcut_transactions" value="1" {{ get_setting($settings, 'pwa_shortcut_transactions') == '1' ? 'checked' : '' }}>
											<label class="custom-control-label" for="pwa_shortcut_transactions">{{ _lang('Transactions Shortcut') }}</label>
										</div>
									</div>
								</div>

								<div class="col-md-4">
									<div class="form-group">
										<div class="custom-control custom-switch">
											<input type="checkbox" class="custom-control-input" id="pwa_shortcut_profile" name="pwa_shortcut_profile" value="1" {{ get_setting($settings, 'pwa_shortcut_profile') == '1' ? 'checked' : '' }}>
											<label class="custom-control-label" for="pwa_shortcut_profile">{{ _lang('Profile Shortcut') }}</label>
										</div>
									</div>
								</div>
							</div>

							<div class="row">
								<div class="col-md-12">
									<h6 class="mt-4 mb-3">{{ _lang('Advanced Settings') }}</h6>
								</div>
							</div>

							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<div class="custom-control custom-switch">
											<input type="checkbox" class="custom-control-input" id="pwa_offline_support" name="pwa_offline_support" value="1" {{ get_setting($settings, 'pwa_offline_support') == '1' ? 'checked' : '' }}>
											<label class="custom-control-label" for="pwa_offline_support">{{ _lang('Offline Support') }}</label>
										</div>
										<small class="form-text text-muted">{{ _lang('Enable offline functionality and caching') }}</small>
									</div>
								</div>

								<div class="col-md-6">
									<div class="form-group">
										<label class="control-label">{{ _lang('Cache Strategy') }}</label>
										<select class="form-control auto-select" name="pwa_cache_strategy" data-selected="{{ get_setting($settings, 'pwa_cache_strategy', 'cache-first') }}">
											<option value="cache-first">{{ _lang('Cache First') }}</option>
											<option value="network-first">{{ _lang('Network First') }}</option>
											<option value="stale-while-revalidate">{{ _lang('Stale While Revalidate') }}</option>
										</select>
										<small class="form-text text-muted">{{ _lang('Strategy for caching resources') }}</small>
									</div>
								</div>
							</div>

							<div class="row">
								<div class="col-md-12">
									<div class="form-group">
										<button type="submit" class="btn btn-primary">{{ _lang('Save Settings') }}</button>
										<a href="{{ route('pwa.test') }}" class="btn btn-info ml-2">{{ _lang('Test PWA') }}</a>
										<button type="button" class="btn btn-success ml-2" onclick="generateIcons()">{{ _lang('Generate Icons') }}</button>
									</div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>

		</div>
	</div>
</div>
@endsection

@section('js-script')
<script>
(function($) {
    "use strict";
	
	function getQueryParam(name) {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }

    function updateQueryParam(tabName) {
        var newUrl = window.location.pathname + "?tab=" + tabName;
        history.replaceState(null, null, newUrl);
    }

    // Get tab name from query string
    var tabName = getQueryParam("tab");

    if (tabName) {
        var $tabLink = $('.nav-tabs a[href="#' + tabName + '"]');
        if ($tabLink.length) {
            $tabLink.tab("show");
        }
    } else {
        $('.nav-tabs a:first').tab("show");
    }

    // Update query string on tab click
    $(".nav-tabs [data-toggle='tab']").on("click", function (e) {
        e.preventDefault();
        $(this).tab("show");

        var tabId = $(this).attr("href").replace("#", "");
        updateQueryParam(tabId);
    });
    
    // PWA Icon Generation Function
    window.generateIcons = function() {
        $.toast({
            heading: '{{ _lang("Icon Generation") }}',
            text: '{{ _lang("Please upload a high-resolution logo (512x512 or larger) to generate PWA icons automatically") }}',
            position: 'top-right',
            loaderBg: '#ff6849',
            icon: 'info',
            hideAfter: 5000,
            stack: 6
        });
    };
})(jQuery);
</script>
@endsection

