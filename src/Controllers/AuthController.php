<?php

namespace Myth\Auth\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Session\Session;
use Myth\Auth\Config\Auth as AuthConfig;
use Myth\Auth\Entities\User;
use Myth\Auth\Models\UserModel;
use Config\Email;

use \RobThree\Auth\TwoFactorAuth as TwoFactorAuth;

class AuthController extends Controller
{
    /**
     * Analysis assist; remove after CodeIgniter 4.3 release.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    protected $auth;

    /**
     * @var AuthConfig
     */
    protected $config;

    /**
     * @var Session
     */
    protected $session;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Most services in this controller require
        // the session to be started - so fire it up!
        $this->session = service('session');
        $this->config = config('Auth');
        $this->auth   = service('authentication');
        $session = session();
    }

    // --------------------------------------------------------------------
    // Login/out
    // --------------------------------------------------------------------
    /**
     * Displays the login form, or redirects
     * the user to their destination/home if
     * they are already logged in.
     *
     * @return RedirectResponse|string
     */
    public function login()
    {
        // No need to show a login form if the user
        // is already logged in.
        if ($this->auth->check()) {
            $redirectURL = session('redirect_url') ?? site_url($this->config->landingRoute);
            unset($_SESSION['redirect_url']);

            return redirect()
                ->to($redirectURL);
        }

        // Set a return URL if none is specified.
        $_SESSION['redirect_url'] = session('redirect_url') ?? previous_url();

        // Display the login view.
        return $this->_render($this->config->views['login'], ['config' => $this->config]);
    }

    /**
     * Attempts to verify the user's credentials
     * through a POST request.
     *
     * @return RedirectResponse
     */
    public function attemptLogin()
    {
        $rules = [
            'login'    => 'required',
            'password' => 'required',
        ];
        if ($this->config->validFields === ['email']) {
            $rules['login'] .= '|valid_email';
        }

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $login    = $this->request->getPost('login');
        $password = $this->request->getPost('password');
        $remember = (bool) $this->request->getPost('remember');

        // Determine credential type
        $type = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Try to log them in...
        if (! $this->auth->attempt([$type => $login, 'password' => $password], $remember)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $this->auth->error() ?? lang('Auth.badAttempt'));
        }

        // Is the user being forced to reset their password?
        if ($this->auth->user()->force_pass_reset === true) {
            $url = route_to('reset-password') . '?token=' . $this->auth->user()->reset_hash;

            return redirect()
                ->to($url)
                ->withCookies();
        }

        $redirectURL = session('redirect_url') ?? site_url($this->config->landingRoute);
        unset($_SESSION['redirect_url']);

        return redirect()
            ->to($redirectURL)
            ->withCookies()
            ->with('message', lang('Auth.loginSuccess'));
    }

    /**
     * Log the user out.
     *
     * @return RedirectResponse
     */
    public function logout()
    {
        if ($this->auth->check()) {
            $cookie_name = "tfa_trust_this_device";
            header("Set-Cookie: {$cookie_name}=; path=/; expires=" . gmdate('D, d M Y H:i:s \G\M\T', time() - 1000) . "; Secure; SameSite=Strict");
            $this->auth->logout();
        }

        return redirect()->to(site_url('/'));
    }

    // --------------------------------------------------------------------
    // Register
    // --------------------------------------------------------------------
    /**
     * Displays the user registration page.
     *
     * @return RedirectResponse|string
     */
    public function register()
    {
        // check if already logged in.
        if ($this->auth->check()) {
            return redirect()->back();
        }

        // Check if registration is allowed
        if (! $this->config->allowRegistration) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', lang('Auth.registerDisabled'));
        }

        return $this->_render($this->config->views['register'], ['config' => $this->config]);
    }

    /**
     * Attempt to register a new user.
     *
     * @return RedirectResponse
     */
    public function attemptRegister()
    {
        // Check if registration is allowed
        if (! $this->config->allowRegistration) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', lang('Auth.registerDisabled'));
        }

        $users = model(UserModel::class);

        // Validate basics first since some password rules rely on these fields
        $rules = config('Validation')->registrationRules ?? [
            'username' => 'required|alpha_numeric_space|min_length[3]|max_length[30]|is_unique[users.username]',
            'email'    => 'required|valid_email|is_unique[users.email]',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        // Validate passwords since they can only be validated properly here
        $rules = [
            'password'     => 'required|strong_password',
            'pass_confirm' => 'required|matches[password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        // Save the user
        $allowedPostFields = array_merge(['password'], $this->config->validFields, $this->config->personalFields);
        $user              = new User($this->request->getPost($allowedPostFields));

        $this->config->requireActivation === null ? $user->activate() : $user->generateActivateHash();

        // Ensure default group gets assigned if set
        if (! empty($this->config->defaultUserGroup)) {
            $users = $users->withGroup($this->config->defaultUserGroup);
        }

        if (! $users->save($user)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $users->errors());
        }

        if ($this->config->requireActivation !== null) {
            $activator = service('activator');
            $sent      = $activator->send($user);

            if (! $sent) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', $activator->error() ?? lang('Auth.unknownError'));
            }

            // Success!
            return redirect()
                ->route('login')
                ->with('message', lang('Auth.activationSuccess'));
        }

        // Success!
        return redirect()
            ->route('login')
            ->with('message', lang('Auth.registerSuccess'));
    }

    // --------------------------------------------------------------------
    // Forgot Password
    // --------------------------------------------------------------------
    /**
     * Displays the forgot password form.
     *
     * @return RedirectResponse|string
     */
    public function forgotPassword()
    {
        if ($this->config->activeResetter === null) {
            return redirect()
                ->route('login')
                ->with('error', lang('Auth.forgotDisabled'));
        }

        return $this->_render($this->config->views['forgot'], ['config' => $this->config]);
    }

    /**
     * Attempts to find a user account with that password
     * and send password reset instructions to them.
     *
     * @return RedirectResponse
     */
    public function attemptForgot()
    {
        if ($this->config->activeResetter === null) {
            return redirect()
                ->route('login')
                ->with('error', lang('Auth.forgotDisabled'));
        }

        $rules = [
            'email' => [
                'label' => lang('Auth.emailAddress'),
                'rules' => 'required|valid_email',
            ],
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $users = model(UserModel::class);

        $user = $users->where('email', $this->request->getPost('email'))->first();

        if (null === $user) {
            return redirect()
                ->back()
                ->with('error', lang('Auth.forgotNoUser'));
        }

        // Save the reset hash /
        $user->generateResetHash();
        $users->save($user);

        $resetter = service('resetter');
        $sent     = $resetter->send($user);

        if (! $sent) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $resetter->error() ?? lang('Auth.unknownError'));
        }

        return redirect()
            ->route('reset-password')
            ->with('message', lang('Auth.forgotEmailSent'));
    }

    /**
     * Displays the Reset Password form.
     *
     * @return RedirectResponse|string
     */
    public function resetPassword()
    {
        if ($this->config->activeResetter === null) {
            return redirect()
                ->route('login')
                ->with('error', lang('Auth.forgotDisabled'));
        }

        $token = $this->request->getGet('token');

        return $this->_render($this->config->views['reset'], [
            'config' => $this->config,
            'token'  => $token,
        ]);
    }

    /**
     * Verifies the code with the email and saves the new password,
     * if they all pass validation.
     *
     * @return RedirectResponse
     */
    public function attemptReset()
    {
        if ($this->config->activeResetter === null) {
            return redirect()
                ->route('login')
                ->with('error', lang('Auth.forgotDisabled'));
        }

        $users = model(UserModel::class);

        // First things first - log the reset attempt.
        $users->logResetAttempt(
            $this->request->getPost('email'),
            $this->request->getPost('token'),
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent()
        );

        $rules = [
            'token'        => 'required',
            'email'        => 'required|valid_email',
            'password'     => 'required|strong_password',
            'pass_confirm' => 'required|matches[password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $user = $users->where('email', $this->request->getPost('email'))
            ->where('reset_hash', $this->request->getPost('token'))
            ->first();

        if (null === $user) {
            return redirect()
                ->back()
                ->with('error', lang('Auth.forgotNoUser'));
        }

        // Reset token still valid?
        if (! empty($user->reset_expires) && time() > $user->reset_expires->getTimestamp()) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', lang('Auth.resetTokenExpired'));
        }

        // Success! Save the new password, and cleanup the reset hash.
        $user->password         = $this->request->getPost('password');
        $user->reset_hash       = null;
        $user->reset_at         = date('Y-m-d H:i:s');
        $user->reset_expires    = null;
        $user->force_pass_reset = false;
        $users->save($user);

        return redirect()
            ->route('login')
            ->with('message', lang('Auth.resetSuccess'));
    }

    /**
     * Activate account.
     *
     * @return mixed
     */
    public function activateAccount()
    {
        $users = model(UserModel::class);

        // First things first - log the activation attempt.
        $users->logActivationAttempt(
            $this->request->getGet('token'),
            $this->request->getIPAddress(),
            (string) $this->request->getUserAgent()
        );

        $throttler = service('throttler');

        if ($throttler->check(md5($this->request->getIPAddress()), 2, MINUTE) === false) {
            return service('response')
                ->setStatusCode(429)
                ->setBody(lang('Auth.tooManyRequests', [$throttler->getTokentime()]));
        }

        $user = $users->where('activate_hash', $this->request->getGet('token'))
            ->where('active', 0)
            ->first();

        if (null === $user) {
            return redirect()
                ->route('login')
                ->with('error', lang('Auth.activationNoUser'));
        }

        $user->activate();

        $users->save($user);

        return redirect()
            ->route('login')
            ->with('message', lang('Auth.registerSuccess'));
    }

    /**
     * Resend activation account.
     *
     * @return mixed
     */
    public function resendActivateAccount()
    {
        if ($this->config->requireActivation === null) {
            return redirect()
                ->route('login');
        }

        $throttler = service('throttler');

        if ($throttler->check(md5($this->request->getIPAddress()), 2, MINUTE) === false) {
            return service('response')
                ->setStatusCode(429)
                ->setBody(lang('Auth.tooManyRequests', [$throttler->getTokentime()]));
        }

        $login = urldecode($this->request->getGet('login'));
        $type  = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $users = model(UserModel::class);

        $user = $users->where($type, $login)
            ->where('active', 0)
            ->first();

        if (null === $user) {
            return redirect()
                ->route('login')
                ->with('error', lang('Auth.activationNoUser'));
        }

        $activator = service('activator');
        $sent      = $activator->send($user);

        if (! $sent) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $activator->error() ?? lang('Auth.unknownError'));
        }

        // Success!
        return redirect()
            ->route('login')
            ->with('message', lang('Auth.activationSuccess'));
    }

    /**
     * Render the view.
     *
     * @return string
     */
    protected function _render(string $view, array $data = [])
    {
        return view($view, $data);
    }

    public function tfa_setup () {
        if (!$this->config->enable_tfa) {
            return redirect()->to(site_url('/login'));
        }
        
        $user = model(UserModel::class)->where('email', session('tfa_email'))->first();
        if (empty($user)) {
            return redirect()->to(site_url('/login'));
        }

        if (!empty($user->tfa_secret)) {
            return redirect()->to(site_url('/tfa'));
        }

        if ($user->tfa_method == 'email') {
            $tfa_recipient = $user->email;
        } elseif ($user->tfa_method == 'sms') {
            $tfa_recipient = $user->{$this->config->user_mobile_col};
        } else {
            $tfa_recipient = '';
        }

        
        $data = $this->auth->enableTfa($user->id, $user->email);
        $data['formated_secret'] = chunk_split($data['secret'], 4, ' ');
        $data['config'] = $this->config;
        $data['tfa_method'] = empty($user->tfa_method) ? 'authenticator' : $user->tfa_method;
        $data['tfa_recipient'] = $tfa_recipient;

        return $this->_render($this->config->views['tfa_setup'], $data);
    }
    public function tfa_setup_confirm()
    {
        if (!$this->config->enable_tfa) {
            return redirect()->to(site_url('/login'));
        }
        $user = model(UserModel::class)->where('email', session('tfa_email'))->first();
        $tfa_confirm = $this->request->getPost('tfa_confirm');

        if ($user->tfa_method == 'email' || $user->tfa_method == 'sms') {
            if ((time() - strtotime($user->tfa_15_mins_exp)) > (15 * 60 + 60)) {
                //allow for 1 extra minute. written as 15 * 60 + 60 for easy search
                return redirect()->back()->with('error', 'Authentication code has expired. Please click on Send Code to get a new one.');
                die();
            } else {
                if ($tfa_confirm != $user->tfa_15_mins) {
                    echo "Wrong two factor authentication code.";
                    die();
                } else {
                    // model(UserModel::class)->update($user->id, ['tfa_recipient' => $this->request->getPost('tfa_recipient')]);
                    echo "success";
                    session()->remove('tfa_email');
                    $this->auth->login($user);
                    die();
                    return redirect()->to(site_url('/'));
                }
            }
        } else {
            $res = $this->auth->verifyTfaCode($this->request->getPost('secret'), $tfa_confirm);
            if ($res) {
                model(UserModel::class)->update($user->id, ['tfa_secret' => $this->request->getPost('secret')]);
                echo "success";
                session()->remove('tfa_email');
                $this->auth->login($user);
                die();
                return redirect()->to(site_url('/'));
            } else {
                echo "Wrong two factor authentication code";
                die();
                return redirect()->back()->with('error', 'Wrong two factor authentication code');
            }
        }
    }

    public function tfa () {
        if (!$this->config->enable_tfa) {
            return redirect()->to(site_url('/login'));
        }
        $user = model(UserModel::class)->where('email', session('tfa_email'))->first();
        if (empty($user)) {
            return redirect()->to(site_url('/login'));
        }
        $trust_days = $this->config->trust_this_device_duration / (24 * 60 * 60);

        if ($this->config->trust_this_device_duration > 0) {
            $cookie_name = "tfa_trust_this_device";
            if (@$_COOKIE[$cookie_name]) {
                $this->auth->login($user);
                return redirect()->to(site_url('/'));
            }
        }
        $recipient = '';

        if ($user->tfa_method == 'email') {
            $tfa_code = $this->auth->getTfaCode($user->tfa_secret);
            $users = model(UserModel::class);
            $user->tfa_15_mins = $tfa_code;
            $user->tfa_15_mins_exp = date("Y-m-d H:i:s", time() + 15 * 60);
            $users->save($user);
            $res = $this->send_code_email($user->email, $tfa_code);
            if ($res) {
                $recipient = $user->email;
                $parts = explode('@', $recipient);
                $local = $parts[0];
                $domain = $parts[1];
                $obscuredLocal = substr($local, 0, 2) . str_repeat('*', max(0, strlen($local) - 4)) . substr($local, -2);
                $recipient = $obscuredLocal . '@' . $domain;
            } else {
                $recipient = "Error sending email code";
            }
        }

        if ($user->tfa_method == "sms") {

            $tfa_code = $this->auth->getTfaCode($user->tfa_secret);
            $users = model(UserModel::class);
            $user->tfa_15_mins = $tfa_code;
            $user->tfa_15_mins_exp = date("Y-m-d H:i:s", time() + 15 * 60);
            $users->save($user);

            $recipient = $user->{$this->config->user_mobile_col};
            $res = $this->send_code_sms($recipient, $tfa_code);
            if (!$res) {
                $recipient = "Error sending SMS. " . $this->error;
            } else {
                $recipient = str_repeat("*", strlen($recipient) - 3) . substr($recipient, -3);
            }
        }

        return $this->_render($this->config->views['tfa'], ['trust_days' => $trust_days, 'recipient' => $recipient]);
    }

    public function verify_tfa_code () {
        if (!$this->config->enable_tfa) {
            return redirect()->to(site_url('/login'));
        }
        $user = model(UserModel::class)->where('email', session('tfa_email'))->first();
        $tfa = $this->request->getPost('tfa');

        if ($user->tfa_method == 'email' || $user->tfa_method == 'sms') {
            if ((time() - strtotime($user->tfa_15_mins_exp)) > (15 * 60 + 60)) {
                //allow for 1 extra minute. written as 15 * 60 + 60 for easy search
                return redirect()->back()->with('error', 'Authentication code has expired. Refresh the page to get another code.');
                die();
            } else {
                if ($tfa != $user->tfa_15_mins) {
                    echo "Wrong two factor authentication code.";
                    die();
                } else {
                    $cookie_name = "tfa_trust_this_device";
                    if ($this->request->getPost('trust_this_device') == 'true') {
                        $cookie_value = md5($user->password_hash . "_" . $user->email);
                        header("Set-Cookie: {$cookie_name}={$cookie_value}; path=/; expires=" . gmdate('D, d M Y H:i:s \G\M\T', time() + $this->config->trust_this_device_duration) . "; Secure; SameSite=Strict");
                    } else {
                        header("Set-Cookie: {$cookie_name}=; path=/; expires=" . gmdate('D, d M Y H:i:s \G\M\T', time() - 1000) . "; Secure; SameSite=Strict");
                    }
                    model(UserModel::class)->update($user->id, ['tfa_15_mins' => 'used']);
                    echo "success";
                    $this->auth->login($user);
                    die();
                    return redirect()->to(site_url('/'));
                }
            }
        } else {

            $res = $this->auth->verifyTfaCode($user->tfa_secret, $tfa);
            if ($res) {
                $cookie_name = "tfa_trust_this_device";
                if ($this->request->getPost('trust_this_device') == 'true') {
                    $cookie_value = md5($user->password_hash . "_" . $user->email);
                    header("Set-Cookie: {$cookie_name}={$cookie_value}; path=/; expires=" . gmdate('D, d M Y H:i:s \G\M\T', time() + $this->config->trust_this_device_duration) . "; Secure; SameSite=Strict");
                } else {
                    header("Set-Cookie: {$cookie_name}=; path=/; expires=" . gmdate('D, d M Y H:i:s \G\M\T', time() - 1000) . "; Secure; SameSite=Strict");
                }

                echo "success";
                session()->remove('tfa_email');
                $this->auth->login($user);
                die();
                $redirectURL = $this->config->landingRoute ?? '/';
                return redirect($redirectURL);
            } else {
                return redirect()->back()->with('error', 'Wrong two factor authentication code');
                die();
            }
        }
    }

    public function send_tfa_setup_code () {
        $method = $this->request->getGet('method');
        $recipient = $this->request->getGet('recipient');

        $user = model(UserModel::class)->where('email', session('tfa_email'))->first();

        // die(PHP_EOL.__FILE__.__LINE__.PHP_EOL);
        $tfa_code = $this->auth->getTfaCode($user->tfa_secret);

        $users = model(UserModel::class);
        $user->tfa_15_mins = $tfa_code;
        $user->tfa_15_mins_exp = date("Y-m-d H:i:s", time() + 15 * 60);
        $users->save($user);

        // pre_var_dump($tfa_code, $method, $recipient);
        // die(PHP_EOL.__FILE__.__LINE__.PHP_EOL);

        $output = array();
        $output['status'] = "error";
        $output['message'] = 'unknown error';

        if ($method == 'email') {
            $res = $this->send_code_email($recipient, $tfa_code);
            if ($res) {
                $output['status'] = "ok";
                $output['message'] = "Email sent to {$recipient}. The code will expire in 15 mins.";
            } else {
                $output['status'] = "error";
                $output['message'] = "Error sending email to {$recipient}.";
            }
        } elseif ($method == 'sms') {
            $res = $this->send_code_sms($recipient, $tfa_code);
            if ($res) {
                $output['status'] = "ok";
                $output['message'] = "SMS sent to {$recipient}. The code will expire in 15 mins.";
            } else {
                $output['status'] = "error";
                $output['message'] = "Error sending SMS to {$recipient}.";
            }

        } else {
            echo "Unknown method.";
            die(PHP_EOL.__FILE__.__LINE__.PHP_EOL);
        }
        header("Content-type:application/json");
        echo json_encode($output);
        die();
    }

    public function send_code_email ($recipient, $tfa_code) {
        $email  = service('email');
        $config = new Email();

        $sent = $email->setFrom($config->fromEmail, $config->fromName)
            ->setTo($recipient)
            ->setSubject("Two-factor authentication code [{$this->config->tfa_issuer}]")
            ->setMessage(view($this->config->views['tfa_code_email'], ['tfa_code' => $tfa_code]))
            ->setMailType('html')
            ->send();

        if (! $sent) {
            $this->error = "Recipient: [{$recipient}]";

            return false;
        }
        return true;
    }

    public function send_code_sms ($recipient, $tfa_code) {
        $recipient = preg_replace('/[^0-9+]/', '', $recipient);

        // Check if the recipient starts with '0' and replace it with '+61'
        if (strpos($recipient, '0') === 0) {
            $recipient = '+61' . substr($recipient, 1);
        }


        $message = "{$tfa_code} is your authentication code for {$this->config->tfa_issuer}. This code will expire in 15 mins.";
        $res = $this->config->send_sms($this->config->tfa_issuer, $recipient, $message);
        // pre_var_dump($res);
        if ($res['error']['code'] != 'SUCCESS') {
            pre_var_dump($res);
            $this->error = lang('Auth.errorEmailSent', [$recipient]);

            return false;
        }
        return true;
    }

}
