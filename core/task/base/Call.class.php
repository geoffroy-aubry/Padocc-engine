<?php

class Task_Base_Call extends Task {

	protected $aTasks;

	/**
	 * Retourne le nom du tag XML correspondant à cette tâche dans les config projet.
	 *
	 * @return string nom du tag XML correspondant à cette tâche dans les config projet.
	 */
	public static function getTagName () {
		return 'call';
	}

	public function __construct (SimpleXMLElement $oTask, Task_Base_Project $oProject, $sBackupPath, Shell_Interface $oShell, Logger_Interface $oLogger) {
		parent::__construct($oTask, $oProject, $sBackupPath, $oShell, $oLogger);
		$this->aAttributeProperties = array(
			'target' => array('required')
		);
		$oTarget = Tasks::getTarget($this->oProject->getSXE(), $this->aAttributes['target']);
		$this->aTasks = Tasks::getTaskInstances($oTarget, $this->oProject, $sBackupPath, $this->oShell, $this->oLogger); // et non $this->sBackupPath, pour les sous-tâches
	}

	public function check () {
		parent::check();
		foreach ($this->aTasks as $oTask) {
			$oTask->check();
		}
	}

	public function execute () {
		foreach ($this->aTasks as $oTask) {
			$oTask->backup();
			$oTask->execute();
		}
	}

	public function backup () {}
}