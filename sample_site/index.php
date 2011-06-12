<?php
/* */
error_reporting(E_ALL); ini_set('display_errors',true);
/*/
error_reporting(0); ini_set('display_errors',false);
/* */
date_default_timezone_set('America/Mexico_City');
setlocale(LC_ALL, 'es_MX.UTF-8', 'es_ES.UTF-8');

require '../DOF/Main.php';
DOF\Main::setup(array(
	'LOCAL_ROOT' => __DIR__,
	'REMOTE_ROOT' => dirname($_SERVER['PHP_SELF']),
	
	'DOF_PATH' => realpath('../DOF'),
	'GENERIC_TEMPLATES_PATH' => realpath('../sample_site_template'),

	'CREATE_LAYOUT_TEMPLATES' => true,
	'OVERWRITE_LAYOUT_TEMPLATES' => true,
	'USE_LAYOUT_TEMPLATES' => true,
	
	'CREATE_FORM_TEMPLATES' => true,
	'OVERWRITE_FORM_TEMPLATES' => true,
	'USE_FORM_TEMPLATES' => true,
	
	'DATA_STORAGE' => new DOF\DataStorages\MySql('localhost','root','','sample_site'),
));

require DOF\Main::$DOF_PATH . '/PlugIns/dpd/DubroxPhpDebugger.class.php';
$debugger = new Dubrox_PhpDebugger(array(
	// Client-side directory location
	// where Dubrox's PHP Debugger is located
	// used to locate JS and CSS plug-ins
	'tools_dir' => DOF\Main::$DOF_PATH . '/PlugIns/dpd/plugins/',

	// Variable name of the GET or POST or REQUEST
	// used to activate the debugger and pass flags to it
	'name' => 'debug',
	
	// Directory where to store logs of the detected bugs.
	// You can use both relative or absolute path.
	'log_dir' => DOF\Main::$LOCAL_ROOT . 'dpd_logs/',

	// Sets some personal commands sets
	'commands_presets' => array(
		'allp' => 'persistent:on,error_reporting:E_ALL',
	),
));

/**
 * @TODO: allow debugging of this fragment of code.
 */
if(class_exists(DOF\Main::$class) && ($obj = new DOF\Main::$class) && ($obj instanceof DOF\Elements\Abstract_)) {
	echo call_user_func_array(array($obj, DOF\Main::$method), DOF\Main::$params);
} else {
	unset($obj);
	header('HTTP/1.1 403 Access forbidden');
	return;
}
//TODO: stuff

echo $debugger->toHtml();