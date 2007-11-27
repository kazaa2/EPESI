<?php
/**
 * Login class.
 *
 * This class provides for basic login functionality, saves passwords to database and enables password recvery.
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license SPL
 * @package epesi-base-extra
 * @subpackage user-login
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_User_Login extends Module {
	protected $lang;

	public function construct() {
		if(!Base_MaintenanceModeCommon::get_changed())
			$this->set_fast_process();
	}

	private function set_logged($user) {
		Acl::set_user($user); //tag who is logged
		$uid = Base_UserCommon::get_user_id($user);
		Base_UserCommon::set_my_user_id($uid);
	}

	private function new_autologin_id() {
		$user = Acl::get_user();
		$uid = Base_UserCommon::get_my_user_id();
		$autologin_id = md5(mt_rand().(isset($_COOKIE['autologin_id'])?$_COOKIE['autologin_id']:md5($user.$uid)).mt_rand());
		setcookie('autologin_id',$user.' '.$autologin_id,time()+60*60*24*30);
		DB::Execute('UPDATE user_password SET autologin_id=%s WHERE user_login_id=%d',array($autologin_id,$uid));
	}

	private function autologin() {
		if(isset($_COOKIE['autologin_id'])) {
			$arr = explode(' ',$_COOKIE['autologin_id']);
			if(count($arr)==2) {
				list($user,$autologin_id) = $arr;
				$ret = DB::GetOne('SELECT p.autologin_id FROM user_login u JOIN user_password p ON u.id=p.user_login_id WHERE u.login=%s AND u.active=1', array($user));
				if($ret && $ret==$autologin_id) {
					$this->set_logged($user);
					$this->new_autologin_id();
					location(array());
					return true;
				}
			}
		}
		return false;
	}

	public function body() {
		$this->lang = & $this->init_module('Base/Lang');

		//check bans
		$t = Variable::get('host_ban_time');
		if($t>0) {
			$fails = DB::GetOne('SELECT count(*) FROM user_login_ban WHERE failed_on>%d AND from_addr=%s',array(time()-$t,$_SERVER['REMOTE_ADDR']));
			if($fails>=3) {
				print('<a href="'.get_epesi_url().'">'.$this->lang->t('Host banned. Click here to refresh.').'</a>');
				return;
			}
		}

		$theme =  & $this->pack_module('Base/Theme');

		//if logged
		$theme->assign('is_logged_in', Acl::is_user());
		if(Acl::is_user()) {
			if($this->get_unique_href_variable('logout')) {
				DB::Execute('UPDATE user_password SET autologin_id=\'\' WHERE user_login_id=%d',array(Base_UserCommon::get_my_user_id()));
				Acl::set_user();
				Base_UserCommon::set_my_user_id();
				location(array());
			} else {
				$theme->assign('logged_as', $this->lang->t('Logged as <b class="green">%s</b>.',array(Acl::get_user())));
				$theme->assign('logout', '<a '.$this->create_unique_href(array('logout'=>1)).'>'.$this->lang->t('Logout').'</a>');
				$theme->display();
			}
			return;
		}

		if($this->is_back())
		    $this->unset_module_variable('mail_recover_pass');

		//if recover pass
		if($this->get_module_variable_or_unique_href_variable('mail_recover_pass')=='1') {
			$this->recover_pass();
			return;
		}
		if($this->autologin()) return;

		//else just login form
		$form = & $this->init_module('Libs/QuickForm',$this->lang->ht('Logging in'));
		$form->addElement('header', 'login_header', $this->lang->t('Login'));
		$form->addElement('text', 'username', $this->lang->t('Username'),array('id'=>'username'));
		$form->addElement('password', 'password', $this->lang->t('Password'));

		// Display warning about storing a cookie
		$warning=$this->lang->t('Don\'t check this box if you are using public computer!');
		$form->addElement('static','warning',null,$warning);
		$form->addElement('checkbox', 'autologin', '',$this->lang->t('Remember me'));

		$form->addElement('static', 'recover_password', null, '<a '.$this->create_unique_href(array('mail_recover_pass'=>1)).'>'.$this->lang->t('Recover password').'</a>');
		$form->addElement('submit', 'submit_button', $this->lang->ht('Login'), array('class'=>'submit'));

		// register and add a rule to check if a username and password is ok
		$form->registerRule('check_login', 'callback', 'submit_login', 'Base_User_Login');
		$form->addRule('username', $this->lang->t('Login or password incorrect'), 'check_login', $form);

		$form->addRule('username', $this->lang->t('Field required'), 'required');
		$form->addRule('password', $this->lang->t('Field required'), 'required');

		if($form->validate()) {
			$user = $form->exportValue('username');
			$autologin = $form->exportValue('autologin');

			$this->set_logged($user);

			if($autologin)
				$this->new_autologin_id();

			location(array());
		} else {
			$form->assign_theme('form', $theme);
			$theme->display();

			eval_js("focus_by_id('username')");
		}
	}

	public static function submit_login($username, $form) {
		$ret = Base_User_LoginCommon::check_login($username, $form->exportValue('password'));
		if(!$ret) {
			$t = Variable::get('host_ban_time');
			if($t>0) {
				DB::Execute('DELETE FROM user_login_ban WHERE failed_on<=%d',array(time()-$t));
				DB::Execute('INSERT INTO user_login_ban(failed_on,from_addr) VALUES(%d,%s)',array(time(),$_SERVER['REMOTE_ADDR']));
				$fails = DB::GetOne('SELECT count(*) FROM user_login_ban WHERE failed_on>%d AND from_addr=%s',array(time()-$t,$_SERVER['REMOTE_ADDR']));
				if($fails>=3)
					location(array());
			}
		}
		return $ret;
	}

	public function recover_pass() {
		$form = & $this->init_module('Libs/QuickForm',$this->lang->ht('Processing request'));

		$form->addElement('header', null, $this->lang->t('Recover password'));
		$form->addElement('hidden', $this->create_unique_key('mail_recover_pass'), '1');
		$form->addElement('text', 'username', $this->lang->t('Username'));
		$form->addElement('text', 'mail', $this->lang->t('e-mail'));
		$ok_b = & HTML_QuickForm::createElement('submit', 'submit_button', $this->lang->ht('OK'));
		$cancel_b = & HTML_QuickForm::createElement('button', 'cancel_button', $this->lang->ht('Cancel'), $this->create_back_href());
		$form->addGroup(array($ok_b,$cancel_b),'buttons');

		// require a username
		$form->addRule('username', $this->lang->t('A username must be between 3 and 32 chars'), 'rangelength', array(3,32));
		// register and add a rule to check if a username and password is ok
		$form->registerRule('check_username', 'callback', 'check_username_mail_valid', 'Base_User_Login');
		$form->addRule('username', $this->lang->t('Username or e-mail invalid'), 'check_username', $form);
		$form->addRule('username', $this->lang->t('Field required'), 'required');
		//require valid e-mail address
		$form->addRule('mail', $this->lang->t('Field required'), 'required');
		$form->addRule('mail', $this->lang->t('This isn\'t valid e-mail address'), 'email');

		if($form->validate()) {
			if($form->process(array(&$this, 'submit_recover')))
				print($this->lang->t('Mail with password sent.').' <a '.$this->create_back_href().'>'.$this->lang->t('Login').'</a>');
		} else $form->display();
	}

	public static function check_username_mail_valid($username, $form) {
		$mail = $form->getElement('mail')->getValue();
		$ret = DB::Execute('SELECT null FROM user_password p JOIN user_login u ON u.id=p.user_login_id WHERE u.login=%s AND p.mail=%s AND u.active=1',array($username, $mail));
		return $ret->FetchRow()!==false;
	}

	public function submit_recover($data) {
		$mail = $data['mail'];
		$username = $data['username'];
		$pass = generate_password();

		$user_id = Base_UserCommon::get_user_id($username);
		if($user_id===false) {
			print('No such user!');
			return false;
		}

		if(!DB::Execute('UPDATE user_password SET password=%s WHERE user_login_id=%d', array(md5($pass), $user_id))) {
			print($this->lang->t('Unable to update password for user %s.',array($username)));
			return false;
		}

		if(!Base_User_LoginCommon::send_mail_with_password($username, $pass, $mail)) {
			print($this->lang->t('Unable to send e-mail with password. Mail module configuration invalid. Please contact system administrator.'));
			return false;
		}

		return true;
	}
}
?>
