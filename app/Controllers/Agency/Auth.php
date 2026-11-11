<?php
namespace App\Controllers\Agency;

use App\Controllers\BaseController;

final class Auth extends BaseController
{
    protected $helpers = ['url','form'];

    public function login()
    {
        return view('agency/auth/login', [
            'pageTitle' => 'Agency Portal Sign In',
            'error' => session()->getFlashdata('agency_login_error'),
            'agency' => null,
            'authPage' => true,
        ]);
    }

    public function authenticate()
    {
        $identifier = $this->request->getPost('username');
        $password = $this->request->getPost('password');
        if (!is_string($identifier) || !is_string($password) || strlen($identifier) > 100 || strlen($password) > 4096) {
            return redirect()->to(url_to('agency.login'))->with('agency_login_error', 'Unable to sign in. Check your credentials or try again later.');
        }
        try {
            $ok = service('agencyPortalSession')->attempt($identifier, $password, $this->request->getIPAddress());
        } catch (\Throwable $e) {
            log_message('error', 'Agency portal sign in unavailable ({type}).', ['type' => get_class($e)]);
            $ok = false;
        }
        if (!$ok) return redirect()->to(url_to('agency.login'))->with('agency_login_error', 'Unable to sign in. Check your credentials or try again later.');
        $user = service('agencyPortalSession')->user();
        return redirect()->to(site_url(!empty($user['must_change_password']) ? 'agency/change-password' : 'agency/dashboard'));
    }

    public function changePassword()
    {
        $agency = service('agencyPortalSession')->user();
        return view('agency/auth/change_password', [
            'pageTitle' => 'Change Agency Password', 'agency' => $agency,
            'error' => session()->getFlashdata('agency_password_error'),
            'message' => session()->getFlashdata('agency_password_message'),
            'authPage' => false, 'activeNav' => 'account',
        ]);
    }

    public function savePassword()
    {
        $current = $this->request->getPost('current_password');
        $password = $this->request->getPost('password');
        $confirmation = $this->request->getPost('password_confirmation');
        if (!is_string($current) || !is_string($password) || !is_string($confirmation)) {
            return redirect()->to(site_url('agency/change-password'))->with('agency_password_error', 'Enter the current and new password.');
        }
        try {
            service('agencyPortalSession')->changePassword($current, $password, $confirmation);
            return redirect()->to(site_url('agency/dashboard'))->with('agency_password_message', 'Your password has been changed.');
        } catch (\DomainException $e) {
            return redirect()->to(site_url('agency/change-password'))->with('agency_password_error', $e->getMessage());
        } catch (\Throwable $e) {
            log_message('error', 'Agency portal password change unavailable ({type}).', ['type' => get_class($e)]);
            return redirect()->to(site_url('agency/change-password'))->with('agency_password_error', 'Password change could not be completed. Please try again.');
        }
    }

    public function logout()
    {
        service('agencyPortalSession')->logout();
        return redirect()->to(url_to('agency.login'));
    }
}
