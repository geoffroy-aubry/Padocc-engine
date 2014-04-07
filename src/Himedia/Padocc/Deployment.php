<?php

namespace Himedia\Padocc;

use Himedia\Padocc\Properties\PropertiesInterface;
use Himedia\Padocc\Task\Base\ExternalProperty;
use Himedia\Padocc\Task\Base\Project;
use Himedia\Padocc\Task\Base\Target;
use Psr\Log\LoggerInterface;

/**
 * Définit une propriété externe qu'il sera obligatoire de fournir lors de tout déploiement.
 * Cette propriété est par la suite réutilisable dans les attributs possédant le flag ALLOW_PARAMETER.
 * À inclure dans une tâche env ou target.
 *
 * Exemple : <externalproperty name="ref" description="Branch or tag to deploy" />
 *
 * @category TwengaDeploy
 * @package Core
 * @author Geoffroy AUBRY <gaubry@hi-media.com>
 */
class Deployment
{

    /**
     * Instance de services.
     * @var DIContainer
     */
    private $oDIContainer;

    /**
     * Adaptateur de propriétés.
     * @var PropertiesInterface
     */
    protected $oProperties;

    /**
     * Adaptateur de log.
     * @var LoggerInterface
     */
    protected $oLogger;

    /**
     * @var array
     */
    protected $aConfig;

    /**
     * Constructeur.
     */
    public function __construct (DIContainer $oDIContainer)
    {
        $this->oDIContainer = $oDIContainer;
        $this->oLogger = $this->oDIContainer->getLogger();
        $this->oProperties = $this->oDIContainer->getPropertiesAdapter();
        $this->aConfig = $this->oDIContainer->getConfig();
    }

    /**
     * Enregistre les propriétés externes dans l'instance PropertiesInterface.
     *
     * @param array $aExternalProperties tableau associatif nom/valeur des propriétés externes.
     */
    private function setExternalProperties (array $aExternalProperties)
    {
        foreach ($aExternalProperties as $sName => $sValue) {
            $sFullName = ExternalProperty::EXTERNAL_PROPERTY_PREFIX . $sName;
            $this->oProperties->setProperty($sFullName, str_replace('&#0160;', ' ', $sValue));
        }
    }

    /**
     * Exécute le déploiement.
     *
     * @param string $sXmlProjectPath chemin vers le XML de configuration du projet
     * @param string $sEnvName
     * @param string $sExecutionID au format YYYYMMDDHHMMSS_xxxxx, où x est un nombre aléatoire,
     * par exemple '20111026142342_07502'
     * @param array $aExternalProperties tableau associatif nom/valeur des propriétés externes.
     * @param string $sRollbackID identifiant de déploiement sur lequel effectuer un rollback,
     * par exemple '20111026142342_07502'
     */
    public function run ($sXmlProjectPath, $sEnvName, $sExecutionID, array $aExternalProperties, $sRollbackID)
    {
        $sXmlProjectPath = realpath($sXmlProjectPath);
        $this->oProperties
            ->setProperty('project_name', 'toto')
            ->setProperty('environment_name', $sEnvName)
            ->setProperty('execution_id', $sExecutionID)
            ->setProperty('tmpdir', $this->aConfig['dir']['tmp'] . '/deploy_' . $sExecutionID)
            ->setProperty('rollback_id', $sRollbackID);

        $this->setExternalProperties($aExternalProperties);

        $this->oLogger->info("Project loaded: $sXmlProjectPath");
//        $this->oLogger->info("[WARNING] test");
//        throw new \RuntimeException('slkghskgh');
        $oProject = new Project($sXmlProjectPath, $sEnvName, $this->oDIContainer);
        $this->oLogger->info('Check tasks:+++');
//        $this->oLogger->info("[WARNING] test");
        $oProject->setUp();
        $this->oLogger->info('---Execute tasks:+++');
        $oProject->execute();
        $this->oLogger->info('---');
    }
}
