<?php

/**
 * @category TwengaDeploy
 * @package Core
 * @author Geoffroy AUBRY <geoffroy.aubry@twenga.com>
 */
class Task_Extended_GitExport extends Task
{

    /**
     * Retourne le nom du tag XML correspondant à cette tâche dans les config projet.
     *
     * @return string nom du tag XML correspondant à cette tâche dans les config projet.
     */
    public static function getTagName ()
    {
        return 'gitexport';
    }

    /**
     * Tâche de synchronisation sous-jacente.
     * @var Task_Base_Sync
     */
    private $_oSyncTask;

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
        $this->_aAttrProperties = array(
            'repository' => AttributeProperties::FILE | AttributeProperties::REQUIRED,
            'ref' => AttributeProperties::REQUIRED | AttributeProperties::ALLOW_PARAMETER,
            'srcdir' => AttributeProperties::DIR,
            'destdir' => AttributeProperties::DIR | AttributeProperties::REQUIRED
                | AttributeProperties::ALLOW_PARAMETER,
            // TODO AttributeProperties::DIRJOKER abusif ici, mais à cause du multivalué :
            'exclude' => AttributeProperties::FILEJOKER | AttributeProperties::DIRJOKER,
        );

        // Valeur par défaut de l'attribut srcdir :
        if (empty($this->_aAttributes['srcdir'])) {
            $this->_aAttributes['srcdir'] =
                DEPLOYMENT_REPOSITORIES_DIR . '/git/'
                . $this->_oProperties->getProperty('project_name') . '_'
                . $this->_oProperties->getProperty('environment_name') . '_'
                . $this->_sCounter;
        }

        // Création de la tâche de synchronisation sous-jacente :
        $this->_oNumbering->addCounterDivision();
        $sSrcDir = preg_replace('#/$#', '', $this->_aAttributes['srcdir']) . '/*';
        $aSyncAttributes = array(
            'src' => $sSrcDir,
            'destdir' => $this->_aAttributes['destdir'],
        );
        if ( ! empty($this->_aAttributes['exclude'])) {
            $aSyncAttributes['exclude'] = $this->_aAttributes['exclude'];
        }
        $this->_oSyncTask = Task_Base_Sync::getNewInstance($aSyncAttributes, $oProject, $oServiceContainer);
        $this->_oNumbering->removeCounterDivision();
    }

    public function setUp ()
    {
        parent::setUp();
        $this->_oLogger->indent();
        $this->_oSyncTask->setUp();
        $this->_oLogger->unindent();
    }

    protected function _centralExecute ()
    {
        parent::_centralExecute();
        $this->_oLogger->indent();

        $aRef = $this->_processPath($this->_aAttributes['ref']);
        $sRef = $aRef[0];

        $sMsg = "Export '$sRef' reference from '" . $this->_aAttributes['repository'] . "' git repository";
        $this->_oLogger->log($sMsg);
        $this->_oLogger->indent();
        $result = $this->_oShell->exec(
            DEPLOYMENT_BASH_PATH . ' ' . DEPLOYMENT_LIB_DIR . '/gitexport.inc.sh'
            . ' "' . $this->_aAttributes['repository'] . '"'
            . ' "' . $sRef . '"'
            . ' "' . $this->_aAttributes['srcdir'] . '"'
        );
        $this->_oLogger->log(implode("\n", $result));
        $this->_oLogger->unindent();

        $this->_oSyncTask->execute();
        $this->_oLogger->unindent();
    }
}
