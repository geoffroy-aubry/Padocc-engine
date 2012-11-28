<?php

/**
 * Permet de copier un fichier ou un répertoire dans un autre.
 * À inclure dans une tâche env ou target.
 *
 * Exemple : <copy src="/path/to/src" dest="${SERVERS}:/path/to/dest" />
 *
 * @category TwengaDeploy
 * @package Core
 * @author Geoffroy AUBRY <geoffroy.aubry@twenga.com>
 */
class Task_Base_Copy extends Task
{

    /**
     * Retourne le nom du tag XML correspondant à cette tâche dans les config projet.
     *
     * @return string nom du tag XML correspondant à cette tâche dans les config projet.
     */
    public static function getTagName ()
    {
        return 'copy';
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
        $this->_aAttrProperties = array(
            'src' => AttributeProperties::SRC_PATH | AttributeProperties::FILEJOKER | AttributeProperties::REQUIRED,
            'destdir' => AttributeProperties::DIR | AttributeProperties::REQUIRED
        );
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
        // TODO si *|? alors s'assurer qu'il en existe ?
        // TODO droit seulement à \w et / et ' ' ?
        parent::check();

        // Suppression de l'éventuel slash terminal :
        $this->_aAttributes['src'] = preg_replace('#/$#', '', $this->_aAttributes['src']);

        if (
                preg_match('/\*|\?/', $this->_aAttributes['src']) === 0
                && $this->_oShell->getPathStatus($this->_aAttributes['src']) === Shell_PathStatus::STATUS_DIR
        ) {
                $this->_aAttributes['destdir'] .= '/' . substr(strrchr($this->_aAttributes['src'], '/'), 1);
                $this->_aAttributes['src'] .= '/*';
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

        $aSrcPath = $this->_processSimplePath($this->_aAttributes['src']);
        $aDestDirs = $this->_processPath($this->_aAttributes['destdir']);
        foreach ($aDestDirs as $sDestDir) {
            $this->_oShell->copy($aSrcPath, $sDestDir);
        }

        $this->_oLogger->unindent();
    }
}