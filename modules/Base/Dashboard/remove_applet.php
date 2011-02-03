<?php
/**
 * Something like igoogle
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-base
 * @subpackage dashboard
 */
header("Content-type: text/javascript");

define('JS_OUTPUT',1);
define('CID',false); //don't load user session
define('READ_ONLY_SESSION',true);
require_once('../../../include.php');

ModuleManager::load_modules();

if(!Acl::is_user()) {
	Epesi::alert('Session expired, logged out - reloading epesi.');
	Epesi::redirect('');
	Epesi::send_output();
	exit();
}

$default = $_POST['default_dash'];
if($default && !Acl::check('Base_Dashboard','set default dashboard')) {
	Epesi::alert('Permission denied');
	Epesi::send_output();
	exit();
}

if(!$default)
	$user = Acl::get_user();

$id = json_decode($_POST['id']);

Base_DashboardCommon::remove_applet($id, $default);

?>
