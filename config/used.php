<?php
$first  = dirname(__FILE__) . "/config." . $_SERVER['SERVER_NAME'] . ".php";

if (file_exists($first)) 
{
	include_once($first);
} 
else 
{
	include_once(dirname(__FILE__) . "/config.php");
}
?>