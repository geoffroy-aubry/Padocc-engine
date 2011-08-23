<?php

class Task_Base_Target extends Task_WithProperties
{

    protected $aTasks;

    /**
     * Retourne le nom du tag XML correspondant à cette tâche dans les config projet.
     *
     * @return string nom du tag XML correspondant à cette tâche dans les config projet.
     */
    public static function getTagName ()
    {
        return 'target';
    }

    // {"rts":["dev","qa","pre-prod"],"tests":["tests_gitexport","tests_languages","all_tests"],"wtpn":["prod"],"ptpn":["prod"]}
    // {"rts":{"dev":[],"qa":[],"pre-prod":[]},"tests":{"tests_gitexport":{"rts_ref":"Branch or tag to deploy"},"tests_languages":{"t1":"Branch","t2":"or tag","t3":"or tag"},"all_tests":[]},"wtpn":{"prod":[]},"ptpn":{"prod":[]}}
    public static function getAvailableTargetsList ($sProjectName)
    {
        $oXMLProject = Task_Base_Project::getProject($sProjectName);
        $aTargets = $oXMLProject->xpath("//env");
        $aTargetsList = array();
        foreach ($aTargets as $oTarget) {
            $sEnvName = (string)$oTarget['name'];

            $aRawExtProperties = $oXMLProject->xpath("//env[@name='$sEnvName']/externalproperty");
            $aExtProperties = array();
            foreach ($aRawExtProperties as $oExternalProperty) {
                $sName = (string)$oExternalProperty['name'];
                $sDesc = (string)$oExternalProperty['description'];
                $aExtProperties[$sName] = $sDesc;
            }
            $aTargetsList[$sEnvName] = $aExtProperties;
        }

        return $aTargetsList;
    }

    /**
     * Constructeur.
     *
     * @param SimpleXMLElement $oTask Contenu XML de la tâche.
     * @param Task_Base_Project $oProject Super tâche projet.
     * @param string $sBackupPath répertoire hôte pour le backup de la tâche.
     * @param ServiceContainer $oServiceContainer Register de services prédéfinis (Shell_Interface, Logger_Interface, ...).
     */
    public function __construct (SimpleXMLElement $oTask, Task_Base_Project $oProject, $sBackupPath, ServiceContainer $oServiceContainer)
    {
        parent::__construct($oTask, $oProject, $sBackupPath, $oServiceContainer);
        $this->aAttributeProperties = array_merge($this->aAttributeProperties, array(
            'name' => Task::ATTRIBUTE_REQUIRED,
        ));

        $this->oNumbering->addCounterDivision();
        $this->aTasks = $this->getTaskInstances($oTask, $this->oProject, $sBackupPath); // et non $this->sBackupPath, pour les sous-tâches
        $this->oNumbering->removeCounterDivision();
    }

    /**
     *
     * Enter description here ...
     * @var array
     * @see getAvailableTasks()
     */
    private static $aAvailableTasks = array();

    /**
     * Retourne un tableau associatif décrivant les tâches disponibles.
     *
     * @return array tableau associatif des tâches disponibles : array('sTag' => 'sClassName', ...)
     * @throws LogicException si collision de nom de tag XML
     */
    private static function getAvailableTasks ()
    {
        if (count(self::$aAvailableTasks) === 0) {
            $aAvailableTasks = array();
            foreach (array('base', 'extended') as $sTaskType) {
                $sTaskPaths = glob(DEPLOYMENT_TASKS_DIR . "/$sTaskType/*.class.php");
                foreach ($sTaskPaths as $sTaskPath) {
                    $sClassName = strstr(substr(strrchr($sTaskPath, '/'), 1), '.', true);
                    $sFullClassName = 'Task_' . ucfirst($sTaskType) . '_' . $sClassName;
                    $sTag = $sFullClassName::getTagName();
                    if (isset($aAvailableTasks[$sTag])) {
                        throw new LogicException("Already defined task tag '$sTag' in '$aAvailableTasks[$sTag]'!");
                    } else if ($sTag != 'project') {
                        $aAvailableTasks[$sTag] = $sFullClassName;
                    }
                }
            }
            self::$aAvailableTasks = $aAvailableTasks;
        }
        return self::$aAvailableTasks;
    }

    /**
     * Retourne la liste des instances de tâches correspondant à chacune des tâches XML devant être exécutée
     * à l'intérieur du noeud XML spécifié.
     *
     * @param SimpleXMLElement $oTarget
     * @param Task_Base_Project $oProject
     * @param string $sBackupPath
     * @return array liste d'instances de type Task
     * @throws Exception si tag XML inconnu.
     * @see Task
     */
    private function getTaskInstances (SimpleXMLElement $oTarget, Task_Base_Project $oProject, $sBackupPath)
    {
        $this->oLogger->log('Initialize tasks');
        $aAvailableTasks = self::getAvailableTasks();

        // Mise à plat des tâches car SimpleXML regroupe celles successives de même nom
        // dans un tableau et les autres sont hors tableau :
        $aTasks = array();
        foreach ($oTarget->children() as $sTag => $mTasks) {
            if (is_array($mTasks)) {
                foreach ($mTasks as $oTask) {
                    $aTasks[] = array($sTag, $oTask);
                }
            } else {
                $aTasks[] = array($sTag, $mTasks);
            }
        }

        // Création des instances de tâches :
        $aTaskInstances = array();
        foreach ($aTasks as $aTask) {
            list($sTag, $oTask) = $aTask;
            if ( ! isset($aAvailableTasks[$sTag])) {
                throw new UnexpectedValueException("Unkown task tag: '$sTag'!");
            } else {
                $aTaskInstances[] = new $aAvailableTasks[$sTag]($oTask, $oProject, $sBackupPath, $this->oServiceContainer);
            }
        }

        return $aTaskInstances;
    }

    /**
     * Vérifie au moyen de tests basiques que la tâche peut être exécutée.
     * Lance une exception si tel n'est pas le cas.
     *
     * Comme toute les tâches sont vérifiées avant que la première ne soit exécutée,
     * doit permettre de remonter au plus tôt tout dysfonctionnement.
     * Appelé avant la méthode execute().
     *
     * @throws UnexpectedValueException en cas d'attribut ou fichier manquant
     * @throws DomainException en cas de valeur non permise
     */
    public function check ()
    {
        parent::check();

        if ( ! empty($this->aAttributes['mailto'])) {
            $this->aAttributes['mailto'] = str_replace(array(';', ','), array(' ', ' '), trim($this->aAttributes['mailto']));
            $this->aAttributes['mailto'] = preg_replace('/\s{2,}/', ' ', $this->aAttributes['mailto']);
        }
    }

    public function setUp ()
    {
        parent::setUp();
        $this->oLogger->indent();
        foreach ($this->aTasks as $oTask) {
            $oTask->setUp();
        }
        $this->oLogger->unindent();
    }

    protected function _preExecute () {
        parent::_preExecute();
        if ( ! empty($this->aAttributes['mailto'])) {
            $this->oLogger->indent();
            $this->oLogger->log('[MAILTO] ' . $this->aAttributes['mailto']);
            $this->oLogger->unindent();
        }
    }

    protected function _centralExecute ()
    {
        parent::_centralExecute();
        $this->oLogger->indent();
        foreach ($this->aTasks as $oTask) {
            $oTask->backup();
            $oTask->execute();
        }
        $this->oLogger->unindent();
    }

    public function backup ()
    {
    }
}
