<?php

namespace App\Http\Controllers\Auth;

use App\Utilities\Overrider;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

class ForgotPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    public function __construct()
    {  
        Overrider::load("Settings");
    }

    protected function credentials(Request $request)
    {
        return $request->only('email');

        if ($request->is('admin/*')) {
            return [
                'email'     => $request->only('email'),
                'tenant_id' => null,
            ];
        } else {
            $tenant = app('tenant');
            return [
                'email'     => $request->only('email'),
                'tenant_id' => $tenant->id,
            ];
        }
    }
    
}
