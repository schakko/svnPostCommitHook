<?php
include_once("Zend/Registry.php");
include_once(dirname(__FILE__) . "/config/used.php");
include_once(dirname(__FILE__) . "/svnPostCommitHook.util.php");
include_once(dirname(__FILE__) . "/svnPostCommitHook.interface.php");

// post-commit hook submits two arguments: 
//  * first is name of repository
//  * second is revision of commit


if (!isset($argv) && isset($_SERVER))
{ 
	$REPOSITORY = $_GET['repository'];
	$REVISION = $_GET['revision'];
}
else
{
	$REPOSITORY = $argv[1];
	$REVISION = $argv[2];
}

try
{
	if (!$REVISION)
	{
		$REVISION = 2;
	}
	
    $runner = new SvnHookRunner(basename($REPOSITORY), $REVISION, Zend_Registry::get('environment'), Zend_Registry::get('repositories'));
    $runner->run();
}
catch (Exception $e)
{
    die("svnPostComitHook failed: " . $e->getMessage());
}
?>