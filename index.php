<?php
// An even simpler version of the index page than version 1. All the actual work of
// determining what needs to be included and what needs to be run is now in the main class.
// Also, 'register_globals' doesn't need to be 'on' anymore.

require_once("ezmlm.php");

$ezmlm = new ezmlm_php();

$action = ($_POST['action'] ? $_POST['action'] : ($_GET['action'] ? $_GET['action'] : "list_info"));
$actionargs = ($_POST['actionargs'] ? $_POST['actionargs'] : ($_GET['actionargs'] ? $_GET['actionargs'] : ""));

$ezmlm->set_action($action);
$ezmlm->set_actionargs($actionargs);
$ezmlm->run();

unset($ezmlm);

?>
