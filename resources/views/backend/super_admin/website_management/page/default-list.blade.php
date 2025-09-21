@extends('layouts.app')

@section('content')
<div class="row">
	<div class="col-lg-12">
		<div class="card">
		    <div class="card-header d-flex align-items-center">
				<span class="panel-title">{{ _lang('Default Pages') }}</span>
			</div>
			<div class="card-body p-0">
				<table class="table table-striped">
					<thead>
					    <tr>
						    <th class="pl-4">{{ _lang('Page') }}</th>
							<th class="text-center">{{ _lang('Action') }}</th>
					    </tr>
					</thead>
					<tbody>
					    <tr>
							<td class="pl-4">{{ _lang('Home Page') }}</td>
							<td class="text-center">
								<a href="{{ route('admin.pages.default_pages', 'home') }}" class="btn btn-outline-primary btn-xs"><i class="ti-pencil-alt mr-1"></i>{{ _lang('Edit') }}</a>
							</td>
					    </tr>
						<tr>
							<td class="pl-4">{{ _lang('About Page') }}</td>
							<td class="text-center">
								<a href="{{ route('admin.pages.default_pages', 'about') }}" class="btn btn-outline-primary btn-xs"><i class="ti-pencil-alt mr-1"></i>{{ _lang('Edit') }}</a>
							</td>
					    </tr>
						<tr>
							<td class="pl-4">{{ _lang('Feature Page') }}</td>
							<td class="text-center">
								<a href="{{ route('admin.pages.default_pages', 'features') }}" class="btn btn-outline-primary btn-xs"><i class="ti-pencil-alt mr-1"></i>{{ _lang('Edit') }}</a>
							</td>
					    </tr>
						<tr>
							<td class="pl-4">{{ _lang('Pricing Page') }}</td>
							<td class="text-center">
								<a href="{{ route('admin.pages.default_pages', 'pricing') }}" class="btn btn-outline-primary btn-xs"><i class="ti-pencil-alt mr-1"></i>{{ _lang('Edit') }}</a>
							</td>
					    </tr>
						<tr>
							<td class="pl-4">{{ _lang('Blog Page') }}</td>
							<td class="text-center">
								<a href="{{ route('admin.pages.default_pages', 'blogs') }}" class="btn btn-outline-primary btn-xs"><i class="ti-pencil-alt mr-1"></i>{{ _lang('Edit') }}</a>
							</td>
					    </tr>
						<tr>
							<td class="pl-4">{{ _lang('FAQ Page') }}</td>
							<td class="text-center">
								<a href="{{ route('admin.pages.default_pages', 'faq') }}" class="btn btn-outline-primary btn-xs"><i class="ti-pencil-alt mr-1"></i>{{ _lang('Edit') }}</a>
							</td>
					    </tr>
						<tr>
							<td class="pl-4">{{ _lang('Contact Page') }}</td>
							<td class="text-center">
								<a href="{{ route('admin.pages.default_pages', 'contact') }}" class="btn btn-outline-primary btn-xs"><i class="ti-pencil-alt mr-1"></i>{{ _lang('Edit') }}</a>
							</td>
					    </tr>
						<tr>
							<td class="pl-4">{{ _lang('Terms & Conditions') }}</td>
							<td class="text-center">
								<a href="{{ route('admin.pages.default_pages', 'terms-condition') }}" class="btn btn-outline-primary btn-xs"><i class="ti-pencil-alt mr-1"></i>{{ _lang('Edit') }}</a>
							</td>
					    </tr>
						<tr>
							<td class="pl-4">{{ _lang('Privacy Policy') }}</td>
							<td class="text-center">
								<a href="{{ route('admin.pages.default_pages', 'privacy-policy') }}" class="btn btn-outline-primary btn-xs"><i class="ti-pencil-alt mr-1"></i>{{ _lang('Edit') }}</a>
							</td>
					    </tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
@endsection