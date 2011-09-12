<?php

/**
 *
 * Dérive Task_WithProperties et supporte donc les attributs XML 'loadtwengaservers', 'propertyshellfile'
 * et 'propertyinifile'.
 *
 * @category TwengaDeploy
 * @package Core
 * @author Geoffroy AUBRY <geoffroy.aubry@twenga.com>
 */
class Task_Base_Target extends Task_WithProperties
{

    protected $_aTasks;

    /**
     * Retourne le nom du tag XML correspondant à cette tâche dans les config projet.
     *
     * @return string nom du tag XML correspondant à cette tâche dans les config projet.
     */
    public static function getTagName ()
    {
        return 'target';
    }

    /* Structure :
     * {
     * 		"rts":{"dev":[],"qa":[],"pre-prod":[]},
     * 		"tests":{
     * 			"tests_gitexport":{"rts_ref":"Branch or tag to deploy"},
     * 			"tests_languages":{"t1":"Branch","t2":"or tag","t3":"or tag"},
     * 			"all_tests":[]},
     * 		"ptpn":{"prod":[]}
     * }
     */
    public static function getAvailableTargetsList ($sProjectName)
    {
        $oXMLProject = Task_Base_Project::getSXEProject(DEPLOYMENT_RESOURCES_DIR . '/' . $sProjectName . '.xml');
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
     * @param ServiceContainer $oServiceContainer Register de services prédéfinis (Shell_Interface, ...).
     */
    public function __construct (SimpleXMLElement $oTask, Task_Base_Project $oProject,
        ServiceContainer $oServiceContainer)
    {
        parent::__construct($oTask, $oProject, $oServiceContainer);
        $this->_aAttrProperties = array_merge(
            $this->_aAttrProperties,
            array('name' => AttributeProperties::REQUIRED)
        );

        $this->_oNumbering->addCounterDivision();
        $this->_aTasks = $this->_getTaskInstances($oTask, $this->_oProject);
        $this->_oNumbering->removeCounterDivision();
    }

    /**
     *
     * Enter description here ...
     * @var array
     * @see getAvailableTasks()
     */
    private static $_aAvailableTasks = array();

    /**
     * Retourne un tableau associatif décrivant les tâches disponibles.
     *
     * @return array tableau associatif des tâches disponibles : array('sTag' => 'sClassName', ...)
     * @throws LogicException si collision de nom de tag XML
     */
    private static function _getAvailableTasks ()
    {
        if (count(self::$_aAvailableTasks) === 0) {
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
            self::$_aAvailableTasks = $aAvailableTasks;
        }
        return self::$_aAvailableTasks;
    }

    /**
     * Retourne la liste des instances de tâches correspondant à chacune des tâches XML devant être exécutée
     * à l'intérieur du noeud XML spécifié.
     *
     * @param SimpleXMLElement $oTarget
     * @param Task_Base_Project $oProject
     * @return array liste d'instances de type Task
     * @throws Exception si tag XML inconnu.
     * @see Task
     */
    private function _getTaskInstances (SimpleXMLElement $oTarget, Task_Base_Project $oProject)
    {
        $this->_oLogger->log('Initialize tasks');
        $aAvailableTasks = self::_getAvailableTasks();

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
                $aTaskInstances[] = new $aAvailableTasks[$sTag]($oTask, $oProject, $this->_oServiceContainer);
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

        if ( ! empty($this->_aAttributes['mailto'])) {
            $aSplittedValues = preg_split(
                AttributeProperties::$sMultiValuedSep,
                trim($this->_aAttributes['mailto']),
                -1,
                PREG_SPLIT_NO_EMPTY
            );
            $this->_aAttributes['mailto'] = implode(' ', $aSplittedValues);
        }
    }

    /**
     * Prépare la tâche avant exécution : vérifications basiques, analyse des serveurs concernés...
     */
    public function setUp ()
    {
        parent::setUp();
        $this->_oLogger->indent();
        foreach ($this->_aTasks as $oTask) {
            $oTask->setUp();
        }
        $this->_oLogger->unindent();
    }

    /**
     * Phase de pré-traitements de l'exécution de la tâche.
     * Elle devrait systématiquement commencer par "parent::_preExecute();".
     * Appelé par _execute().
     * @see execute()
     */
    protected function _preExecute ()
    {
        parent::_preExecute();
        if ( ! empty($this->_aAttributes['mailto'])) {
            $this->_oLogger->indent();
            $this->_oLogger->log('[MAILTO] ' . $this->_aAttributes['mailto']);
            $this->_oLogger->unindent();
        }
    }

    /**
     * Phase de traitements centraux de l'exécution de la tâche.
     * Elle devrait systématiquement commencer par "parent::_centralExecute();".
     * Appelé par _execute().
     * @see execute()
     */
    protected function _centralExecute ()
    {
        parent::_centralExecute();
        $this->_oLogger->indent();
        foreach ($this->_aTasks as $oTask) {
            $oTask->execute();
        }
        $this->_oLogger->unindent();
    }
}
