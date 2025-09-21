<div class="js-cookie-consent cookie-consent" id="cookie-consent-box">
    <div class="text-center">
        <p class="mb-2">
            {{ isset($gdpr_cookie_consent->cookie_message) ? $gdpr_cookie_consent->cookie_message : '' }}
        </p>     
        <button type="button" class="btn btn-light btn-sm js-cookie-consent-agree cookie-consent__agree cursor-pointer text-nowrap" id="cookie-accept-btn"><i class="bi bi-check2-all me-2"></i>{{ _lang('Accept') }}</button>
    </div>
</div>
