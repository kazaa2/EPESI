<?php
/**
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-apps
 * @subpackage shoutbox
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_Shoutbox extends Module {

	public function body() {
		//if i am admin add "clear shoutbox" actionbar button
		if(Base_AclCommon::i_am_admin())
			Base_ActionBarCommon::add('delete','Clear shoutbox',$this->create_callback_href(array($this,'delete_all')));

		$this->applet();
	}

	//delete_all callback (on "clear shoutbox" button)
	public function delete_all() {
		DB::Execute('DELETE FROM apps_shoutbox_messages');
	}

	public function applet() {
		Base_ThemeCommon::load_css($this->get_type()); // added by MS
		if(Acl::is_user()) {
			//initialize HTML_QuickForm
			$qf = & $this->init_module('Libs/QuickForm');
			//create text box
			$text = & HTML_QuickForm::createElement('text','post',$this->t('Post'),'id="shoutbox_text"');
			//create submit button
			$submit = & HTML_QuickForm::createElement('submit','button',$this->ht('Submit'), 'id="shoutbox_button"');
			//add it
			$qf->addGroup(array($text,$submit),'post');
			$qf->addGroupRule('post',$this->t('Field required'),'required',null,2);
			$qf->setRequiredNote(null);

			//if submited
			if($qf->validate()) {
				 //get post group
				$msg = $qf->exportValue('post');
				//get msg from post group
				$msg = Utils_BBCodeCommon::optimize($msg['post']);
				//get logged user id
				$user_id = Acl::get_user();
				//clear text box and focus it
				eval_js('$(\'shoutbox_text\').value=\'\';focus_by_id(\'shoutbox_text\')');

				//insert to db
				DB::Execute('INSERT INTO apps_shoutbox_messages(message,base_user_login_id) VALUES(%s,%d)',array(htmlspecialchars($msg,ENT_QUOTES,'UTF-8'),$user_id));
			}
			//display form
			$qf->display();
		} else {
			print($this->t('Please log in to post message').'<br>');
		}

		print('<div id=\'shoutbox_board\'></div>');
		Base_ThemeCommon::load_css($this->get_type());

		//if there is displayed shoutbox, call myFunctions->refresh from refresh.php file every 5s
		eval_js_once('shoutbox_refresh = function(){if(!$(\'shoutbox_board\')) return;'.
			'new Ajax.Updater(\'shoutbox_board\',\'modules/Apps/Shoutbox/refresh.php\',{method:\'get\'});'.
			'};setInterval(\'shoutbox_refresh()\',30000)');
		eval_js('shoutbox_refresh()');
	}

	public function caption() {
		return "Shoutbox";
	}
}

?>
