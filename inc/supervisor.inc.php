<?php

include_once(__DIR__ . '/../conf/config.inc.php');
include_once(CLI_INC_DIR . '/error.inc.php');

// On supprime le 1er paramètre correspondant au nom du script courant :
$argc--;
array_shift($argv);

// On traite le paramètre fichier de log :
if ($argc < 2) {
	throw new PrestadevBadMethodCallException('Missing PHP script to execute or missing error log file!');
} else {
	$argc--;
	$error_log_file = array_pop($argv);
	errorInit(0, $error_log_file);
}

// Exécution du script PHP :
$script_name = CLI_PHP_SCRIPTS_DIR . '/' . $argv[0];
include_once($script_name);