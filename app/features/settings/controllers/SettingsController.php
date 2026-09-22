<?php

namespace SmartReport\Features\Settings\Controllers;

use SmartReport\Core\App;
use SmartReport\Core\Auth;
use SmartReport\Core\Controller;
use SmartReport\Core\Config;
use SmartReport\Core\Database;
use SmartReport\Core\FeatureRegistry;
use SmartReport\Services\Audit;

class SettingsController extends Controller
{
    private static $ROLES = ['root', 'admin', 'viewer'];

    public function index()
    {
        $this->view(':features/settings/views/index', [
            'title' => t('settings.title'),
            'info' => $this->systemInfo(),
        ]);
    }

    public function users()
    {
        $users = [];
        try {
            $users = Database::main()->fetchAll('SELECT * FROM smr_users ORDER BY role DESC, username ASC');
        } catch (\Exception $e) {
            set_flash('error', $e->getMessage());
        }
        $this->view(':features/settings/views/users', [
            'title' => t('settings.users.title'),
            'users' => $users,
            'roles' => self::$ROLES,
        ]);
    }

    public function userCreate()
    {
        $username = trim((string) $this->post('username', ''));
        $password = (string) $this->post('password', '');
        $display = trim((string) $this->post('display_name', ''));
        $role = (string) $this->post('role', 'viewer');

        if ($username === '') {
            $this->redirect('/settings/users', t('settings.users.username') . ' required', 'error');
        }
        if (strlen($password) < 8) {
            $this->redirect('/settings/users', t('settings.profile.too_short'), 'error');
        }
        if (!in_array($role, self::$ROLES, true)) {
            $role = 'viewer';
        }

        $db = Database::main();
        $exists = $db->fetchValue('SELECT COUNT(*) FROM smr_users WHERE username = ?', [$username]);
        if ((int) $exists > 0) {
            $this->redirect('/settings/users', t('settings.users.username_exists'), 'error');
        }

        $db->insert('smr_users', [
            'username' => $username,
            'password_hash' => smr_password_hash($password),
            'role' => $role,
            'display_name' => $display,
            'active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        Audit::log('user_created', 'user=' . $username . ' role=' . $role);
        $this->redirect('/settings/users', t('settings.users.added'));
    }

    public function userEdit($id)
    {
        $user = $this->findUser((int) $id);
        if ($user === null) {
            $this->redirect('/settings/users', t('settings.users.not_found'), 'error');
        }
        $this->view(':features/settings/views/user_edit', [
            'title' => t('settings.users.edit') . ': ' . $user['username'],
            'user' => $user,
            'roles' => self::$ROLES,
        ]);
    }

    public function userUpdate($id)
    {
        $user = $this->findUser((int) $id);
        if ($user === null) {
            $this->redirect('/settings/users', t('settings.users.not_found'), 'error');
        }
        $db = Database::main();
        $isRoot = $user['role'] === 'root';

        $data = ['updated_at' => date('Y-m-d H:i:s')];

        if (!$isRoot) {
            $role = (string) $this->post('role', $user['role']);
            if (in_array($role, self::$ROLES, true)) {
                $data['role'] = $role;
            }
            $active = $this->post('active', '0') === '1' && (int) $user['id'] !== (int) Auth::user()['id'] ? 1 : $user['active'];
            $data['active'] = $active;
            if ((string) $this->post('username', $user['username']) !== (string) $user['username']) {
                $newName = trim((string) $this->post('username', ''));
                if ($newName !== '') {
                    $exists = $db->fetchValue('SELECT COUNT(*) FROM smr_users WHERE username = ? AND id <> ?', [$newName, (int) $id]);
                    if ((int) $exists > 0) {
                        $this->redirect('/settings/users/' . $id, t('settings.users.username_exists'), 'error');
                    }
                    $data['username'] = $newName;
                }
            }
        }

        $data['display_name'] = trim((string) $this->post('display_name', $user['display_name']));

        $newPassword = (string) $this->post('password', '');
        if ($newPassword !== '') {
            if (strlen($newPassword) < 8) {
                $this->redirect('/settings/users/' . $id, t('settings.profile.too_short'), 'error');
            }
            $data['password_hash'] = smr_password_hash($newPassword);
        }

        $db->update('smr_users', $data, 'id = ?', [(int) $id]);
        $updateUser = isset($data['username']) ? $data['username'] : $user['username'];
        Audit::log('user_updated', 'user=' . $updateUser);
        $this->redirect('/settings/users', t('settings.users.updated'));
    }

    public function userToggle($id)
    {
        $user = $this->findUser((int) $id);
        if ($user === null) {
            $this->redirect('/settings/users', t('settings.users.not_found'), 'error');
        }
        $current = Auth::user();
        if ($user['role'] === 'root' || ($current !== null && (int) $user['id'] === (int) $current['id'])) {
            $this->redirect('/settings/users', t('settings.users.not_found'), 'error');
        }
        Database::main()->update('smr_users', ['active' => (int) $user['active'] === 1 ? 0 : 1], 'id = ?', [(int) $id]);
        Audit::log('user_updated', 'user=' . $user['username'] . ' toggle active');
        $this->redirect('/settings/users');
    }

    public function userDelete($id)
    {
        $user = $this->findUser((int) $id);
        if ($user === null) {
            $this->redirect('/settings/users', t('settings.users.not_found'), 'error');
        }
        $current = Auth::user();
        if ($user['role'] === 'root') {
            $this->redirect('/settings/users', t('settings.users.cannot_delete_root'), 'error');
        }
        if ($current !== null && (int) $user['id'] === (int) $current['id']) {
            $this->redirect('/settings/users', t('settings.users.cannot_delete_self'), 'error');
        }
        Database::main()->delete('smr_users', 'id = ?', [(int) $id]);
        Audit::log('user_deleted', 'user=' . $user['username']);
        $this->redirect('/settings/users', t('settings.users.deleted'));
    }

    public function modules()
    {
        $this->view(':features/settings/views/modules', [
            'title' => t('settings.modules.title'),
            'features' => FeatureRegistry::all(),
            'brandName' => App::name(),
            'showLegs' => (int) App::setting('ui.show_legs', 0) === 1,
            'user' => Auth::user(),
        ]);
    }

    public function modulesSave()
    {
        $enabled = $this->post('enabled', []);
        $enabled = is_array($enabled) ? array_map('strval', $enabled) : [];
        $brandName = trim((string) $this->post('branding_app_name', ''));
        if ($brandName !== '') {
            App::setSetting('branding.app_name', $brandName);
        }
        $currentUser = Auth::user();
        $isRoot = $currentUser !== null && $currentUser['role'] === 'root';
        if ($isRoot) {
            // Call-legs expander is an expert feature; hidden by default.
            App::setSetting('ui.show_legs', $this->post('show_legs', '0') === '1' ? '1' : '0');
        }
        foreach (FeatureRegistry::all() as $id => $feature) {
            if (!empty($feature['locked']) && !$isRoot) {
                continue;
            }
            $want = in_array($id, $enabled, true) ? 1 : 0;
            Database::main()->update('smr_feature_registry', ['enabled' => $want], 'id = ?', [$id]);
        }
        Audit::log('features_updated', 'enabled=' . implode(',', $enabled));
        $this->redirect('/settings/modules', t('settings.modules.saved'));
    }

    public function ami()
    {
        $ami = new \SmartReport\Services\AmiService();
        $testOk = null;
        $testError = '';
        if ($this->query('test', '') === '1') {
            try {
                $channels = $ami->channels();
                $queues = $ami->queues();
                $testOk = $ami->enabled() && ($channels !== [] || $queues !== [] || $ami->lastError() === '');
                $testError = $ami->lastError();
            } catch (\Exception $e) {
                $testOk = false;
                $testError = $e->getMessage();
            }
        }
        $this->view(':features/settings/views/ami', [
            'title' => t('settings.ami.title'),
            'ami' => $ami->configMasked(),
            'amiTestOk' => $testOk,
            'amiTestError' => $testError,
        ]);
    }

    public function profile()
    {
        $this->view(':features/settings/views/profile', [
            'title' => t('settings.profile.title'),
        ]);
    }

    public function profileSave()
    {
        $current = Auth::user();
        if ($current === null) {
            $this->redirect('/login');
        }
        $currentPass = (string) $this->post('current_password', '');
        $newPass = (string) $this->post('new_password', '');
        $confirm = (string) $this->post('confirm_password', '');

        if (!smr_password_verify($currentPass, $current['password_hash'])) {
            $this->redirect('/settings/profile', t('settings.profile.current_wrong'), 'error');
        }
        if (strlen($newPass) < 8) {
            $this->redirect('/settings/profile', t('settings.profile.too_short'), 'error');
        }
        if ($newPass !== $confirm) {
            $this->redirect('/settings/profile', t('settings.profile.mismatch'), 'error');
        }

        Database::main()->update('smr_users', ['password_hash' => smr_password_hash($newPass)], 'id = ?', [(int) $current['id']]);
        Audit::log('password_changed', 'user=' . $current['username']);
        $this->redirect('/settings/profile', t('settings.profile.updated'));
    }

    private function findUser($id)
    {
        return Database::main()->fetchRow('SELECT * FROM smr_users WHERE id = ?', [$id]);
    }

    private function systemInfo()
    {
        $info = [];
        $info['app'] = App::name();
        $info['version'] = App::version();
        $info['php'] = PHP_VERSION;
        $extensions = ['pdo', 'pdo_mysql', 'json', 'session', 'filter', 'openssl'];
        $info['extensions'] = [];
        foreach ($extensions as $ext) {
            $info['extensions'][$ext] = extension_loaded($ext);
        }

        $info['db_main'] = false;
        $info['db_cdr'] = false;
        $info['db_asterisk'] = false;
        $info['monitor'] = ['configured' => false, 'exists' => false, 'readable' => false];
        try {
            Database::main()->fetchValue('SELECT 1');
            $info['db_main'] = true;
        } catch (\Exception $e) {
        }
        try {
            Database::external('cdr')->fetchValue('SELECT 1');
            $info['db_cdr'] = true;
        } catch (\Exception $e) {
        }
        try {
            Database::external('asterisk')->fetchValue('SELECT 1');
            $info['db_asterisk'] = true;
        } catch (\Exception $e) {
        }
        $monitorDir = Config::get('external.monitor_dir', '');
        if (is_string($monitorDir) && $monitorDir !== '') {
            $info['monitor']['configured'] = true;
            $info['monitor']['exists'] = is_dir($monitorDir);
            $info['monitor']['readable'] = is_dir($monitorDir) && is_readable($monitorDir);
        }
        $info['monitor']['path'] = (string) $monitorDir;

        return $info;
    }
}