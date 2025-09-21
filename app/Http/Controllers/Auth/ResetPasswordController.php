<?php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResetPasswordController extends Controller {
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    protected function credentials(Request $request) {
        if ($request->is('admin/*')) {
            return [
                'email'                 => $request->email,
                'password'              => $request->password,
                'password_confirmation' => $request->password_confirmation,
                'token'                 => $request->token,
                'tenant_id'             => null,
            ];
        } else {
            $tenant = app('tenant');
            return [
                'email'                 => $request->email,
                'password'              => $request->password,
                'password_confirmation' => $request->password_confirmation,
                'token'                 => $request->token,
                'tenant_id'             => $tenant->id,
            ];
        }
    }

    protected function sendResetResponse(Request $request, $response) {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => trans($response)], 200);
        }

        if (auth()->user()->user_type == 'superadmin') {
            return redirect()->route('admin.dashboard.index')->with('status', trans($response));
        } else {
            return redirect()->route('dashboard.index', ['tenant' => auth()->user()->tenant->slug])
                ->with('status', trans($response));
        }
    }
}
