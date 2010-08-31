<?php
include_once("Zend/Registry.php");

/**
 * Path to svn executable. Enter the full path
 */
$arrEnvironment['svn']['executable'] = 'your/path/to/svn.exe';

/**
 * Enable logging mechanism
 */
$arrEnvironment['logging']['enable'] = true;

/**
 * Save log to file
 */
$arrEnvironment['logging']['file'] = dirname(__FILE__) . "/../svnPostCommitHook.log";

/**
 * Specifiy logging level
 */
$arrEnvironment['logging']['level'] = array('info', 'error');

/**
 * Enable hooks, you can register your own hook here.
 * Your hook must be located in hooks/YOUR_HOOK/YOUR_HOOK.php. To register, set array('svn2db','YOUR_HOOK');
 */
$arrEnvironment['hooks'] = array(	'svn2db'  //=> array('pathToSqliteDatabase' => '<your path to svn2db.sqlite>'),
									// Your hook => array('your config' => 'value'),
								);

/**
 * Default repository configuration if no special configuration is defined
 */
$arrDefaultRepository['arrHooks'] = $arrEnvironment['hooks'];

/**
 * Default reposiotry URL
 */
$arrDefaultRepository['repositoryUrl'] = 'svn://<your-server>/';

/**
 * All repositories must be written in lower cases!
 * The next line overrides the default repository settings for a specific subversion repository.
 */
// $arrRepositories['override-repository'] = $arrDefaultRepository;

// Do not remove next lines!!!
$arrRepositories['__DEFAULT__'] = $arrDefaultRepository;

Zend_Registry::set('repositories', $arrRepositories);
Zend_Registry::set('environment', $arrEnvironment);
?>