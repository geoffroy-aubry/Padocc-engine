<?php

/**
 * @category TwengaDeploy
 * @package Lib
 * @author Geoffroy AUBRY
 */
class Properties_Adapter implements Properties_Interface
{

    /**
     * @var array
     */
    private $_aProperties;

    /**
     * Shell adapter.
     * @var Shell_Interface
     */
    private $_oShell;

    public function __construct (Shell_Interface $oShell)
    {
        $this->_aProperties = array();
        $this->_oShell = $oShell;
    }

    /**
     * Retourne la valeur de la propriété spécifiée.
     *
     * @param string $sPropertyName propriété dont on recherche la valeur
     * @return string valeur de la propriété spécifiée.
     * @throws UnexpectedValueException si propriété inconnue
     */
    public function getProperty ($sPropertyName)
    {
        if ( ! isset($this->_aProperties[strtolower($sPropertyName)])) {
            throw new UnexpectedValueException("Unknown property '$sPropertyName'!");
        }
        return $this->_aProperties[strtolower($sPropertyName)];
    }

    /**
     * Initialise ou met à jour la valeur de la propriété spécifiée.
     *
     * @param string $sPropertyName propriété
     * @param string $sValue
     * @return Properties_Interface cette instance
     */
    public function setProperty ($sPropertyName, $sValue)
    {
        $this->_aProperties[strtolower($sPropertyName)] = (string)$sValue;
        return $this;
    }

    /**
     * Charge le fichier INI spécifié en ajoutant ou écrasant ses définitions aux propriétés existantes.
     *
     * @param string $sIniPath path du fichier INI à charger
     * @return Properties_Interface cette instance
     * @throws RuntimeException si erreur de chargement du fichier INI
     * @throws UnexpectedValueException si fichier INI introuvable
     */
    public function loadConfigIniFile ($sIniPath)
    {
        if ( ! file_exists($sIniPath)) {
            throw new UnexpectedValueException("Property file '$sIniPath' not found!");
        }

        $aRawProperties = @parse_ini_file($sIniPath);
        if ($aRawProperties === false) {
            throw new RuntimeException("Load property file '$sIniPath' failed: " . print_r(error_get_last(), true));
        }

        // Normalisation :
        $aProperties = array();
        foreach ($aRawProperties as $sProperty => $sValue) {
            $aProperties[strtolower($sProperty)] = $sValue;
        }

        $this->_aProperties = array_merge($this->_aProperties, $aProperties);
        return $this;
    }

    /**
     * Charge le fichier shell spécifié en ajoutant ou écrasant ses définitions aux propriétés existantes.
     *
     * Format du fichier :
     *    PROPRIETE_1="chaîne"
     *    PROPRIETE_2="chaîne $PROPRIETE_1 chaîne"
     *    ...
     *
     * @param string $sConfigShellPath path du fichier shell à charger
     * @return Properties_Interface cette instance
     * @throws RuntimeException si erreur de chargement du fichier
     * @throws UnexpectedValueException si fichier shell introuvable
     */
    public function loadConfigShellFile ($sConfigShellPath)
    {
        if ( ! file_exists($sConfigShellPath)) {
            throw new UnexpectedValueException("Property file '$sConfigShellPath' not found!");
        }
        $sConfigIniPath = DEPLOYMENT_RESOURCES_DIR . strrchr($sConfigShellPath, '/') . '.ini';
        $sCmd = DEPLOYMENT_BASH_PATH . ' ' . __DIR__ . "/cfg2ini.inc.sh '$sConfigShellPath' '$sConfigIniPath'";
        $this->_oShell->exec($sCmd);
        return $this->loadConfigIniFile($sConfigIniPath);
    }
}
