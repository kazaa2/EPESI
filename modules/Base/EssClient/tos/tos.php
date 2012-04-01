<?php

define('CID',false);
require_once('../../../../include.php');
ModuleManager::load_modules();

$lang = Base_LangCommon::get_lang_code();

function filename($lang) {
	return 'modules/Base/EssClient/tos/'.$lang.'_tos.html';
}

if (!file_exists(filename($lang))) $lang = 'en';
$message = file_get_contents(filename($lang));

Utils_FrontPageCommon::display(Base_LangCommon::ts('Base_EssClient','Terms of Service'), $message);

?>