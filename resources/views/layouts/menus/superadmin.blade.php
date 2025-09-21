<li>
	<a href="{{ route('admin.dashboard.index') }}"><i class="fas fa-th-large"></i><span>{{ _lang('Dashboard') }}</span></a>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-user-friends"></i><span>{{ _lang('Tenants') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.tenants.index') }}">{{ _lang('All Tenants') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.tenants.create') }}">{{ _lang('Add New') }}</a></li>
	</ul>
</li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-gift"></i><span>{{ _lang('Subscription Plans') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.packages.index') }}">{{ _lang('All Plans') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.packages.create') }}">{{ _lang('Add New') }}</a></li>
	</ul>
</li>

<li><a href="{{ route('admin.subscription_payments.index') }}"><i class="fas fa-credit-card"></i><span>{{ _lang('Subscription Payments') }}</span>{!! request_count('pending_payments') > 0 ? xss_clean('<div class="circle-animation"></div>') : '' !!}</a></li>
<li><a href="{{ route('admin.payment_gateways.index') }}"><i class="fab fa-cc-paypal"></i><span>{{ _lang('Online Gateways') }}</span></a></li>
<li><a href="{{ route('admin.offline_methods.index') }}"><i class="fas fa-money-bill-alt"></i><span>{{ _lang('Offline Gateways') }}</span></a></li>

<li>
	<a href="javascript: void(0);"><i class="fab fa-firefox-browser"></i><span>{{ _lang('Website Management') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.pages.default_pages') }}">{{ _lang('Default Pages') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.pages.index') }}">{{ _lang('Custom Pages') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.faqs.index') }}">{{ _lang('Manage Faq') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.features.index') }}">{{ _lang('Manage Features') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.testimonials.index') }}">{{ _lang('Manage Testimonials') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.posts.index') }}">{{ _lang('Blog Posts') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.teams.index') }}">{{ _lang('Manage Teams') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.pages.default_pages', 'header_footer') }}">{{ _lang('Header & Footer Settings') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.pages.default_pages', 'gdpr_cookie_consent') }}">{{ _lang('GDPR Cookie Consent') }}</a></li>
	</ul>
</li>

<li><a href="{{ route('admin.email_subscribers.index') }}"><i class="far fa-envelope"></i><span>{{ _lang('Email Subscribers') }}</span></a></li>
<li><a href="{{ route('admin.contact_messages.index') }}"><i class="fas fa-envelope-open-text"></i><span>{{ _lang('Contact Messages') }}</span>{!! xss_clean(request_count('unread_contact_message', true, 'sidebar-notification-count contact-notification-count')) !!}</a></li>

<li>
	<a href="javascript: void(0);"><i class="fas fa-globe"></i><span>{{ _lang('Languages') }}</span><span class="menu-arrow"><i class="mdi mdi-chevron-right"></i></span></a>
	<ul class="nav-second-level" aria-expanded="false">
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.languages.index') }}">{{ _lang('All Language') }}</a></li>
		<li class="nav-item"><a class="nav-link" href="{{ route('admin.languages.create') }}">{{ _lang('Add New') }}</a></li>
	</ul>
</li>

<li><a href="{{ route('admin.settings.update_settings') }}"><i class="fas fa-cog"></i><span>{{ _lang('System Settings') }}</span></a></li>
<li><a href="{{ route('admin.notification_templates.index') }}"><i class="fas fa-envelope-open-text"></i><span>{{ _lang('Notification Templates') }}</span></a></li>
<li><a href="{{ route('admin.backup.index') }}"><i class="fas fa-server"></i><span>{{ _lang('Data Backup & Restore') }}</span></a></li>

<li>
	<a href="{{ route('admin.audit.index') }}"><i class="fas fa-shield-alt"></i><span>{{ _lang('System Audit Trail') }}</span></a>
</li>

<li>
	<a href="{{ route('admin.security.dashboard') }}"><i class="fas fa-shield-virus"></i><span>{{ _lang('Security Dashboard') }}</span></a>
</li>