<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

$ssoready = new SSOReady\SSOReadyClient(
    // Do not hard-code or leak your SSOReady API key in production!
    //
    // In production, instead you should configure a secret SSOREADY_API_KEY
    // environment variable. The SSOReady SDK automatically loads an API key
    // from SSOREADY_API_KEY.
    //
    // This key is hard-coded here for the convenience of logging into a test
    // app, which is hard-coded to run on http://localhost:8000. It's only
    // because of this very specific set of constraints that it's acceptable to
    // hard-code and publicly leak this API key.
    'ssoready_sk_24zq33ln6zbammojrhe0ediap'
);

Route::get('/', function () {
    return view('welcome');
});

// This is the page users visit when they submit the "Log in with SAML" form in
// this demo app.
Route::get('/saml-redirect', function(Request $request) use ($ssoready) {
    // To start a SAML login, you need to redirect your user to their employer's
    // particular Identity Provider. This is called 'initiating' the SAML login.
    //
    // Use `saml->getSAMLRedirectURL` to initiate a SAML login.
    $redirectUrl = $ssoready->saml->getSAMLRedirectURL(new SSOReady\Saml\Requests\GetSamlRedirectUrlRequest([
        // OrganizationExternalId is how you tell SSOReady which company's
        // identity provider you want to redirect to.
        //
        // In this demo, we identify companies using their domain. This code
        // converts 'john.doe@example.com' into 'example.com'.
        'organizationExternalId' => explode('@', $request->input('email'))[1],
    ]))->redirectUrl;

    return redirect($redirectUrl);
});

// This is the page SSOReady redirects your users to when they've successfully
// logged in with SAML.
Route::get('/ssoready-callback', function(Request $request) use ($ssoready) {
    // SSOReady gives you a one-time SAML access code under
    // ?saml_access_code=saml_access_code_... in the callback URL's query
    // parameters.
    //
    // You redeem that SAML access code using `saml->redeemSamlAccessCode`, which
    // gives you back the user's email address. Then, it's your job to log the user
    // in as that email.
    $email = $ssoready->saml->redeemSamlAccessCode(new SSOReady\Saml\Requests\RedeemSamlAccessCodeRequest([
        'samlAccessCode' => $request->input('saml_access_code'),
    ]))->email;

    // SSOReady works with any stack or any session technology you use. 
    //
    // We use the builtin Laravel Auth module and User class in this example.

    // upsert a user by email
    $user = User::firstOrCreate(
        ['email' => $email],
        ['email' => $email, 'name' => $email, 'password' => ''],
    );    
    Auth::login($user); // log in as that user
    return redirect('/');
});

// This is the page users visit when they click on the "Log out" link in this
// demo app. It just uses the Laravel builtin Auth::logout() method.
//
// SSOReady doesn't impose any constraints on how your app's sessions work.
Route::get('/logout', function() {
    Auth::logout();
    return redirect('/');
});
