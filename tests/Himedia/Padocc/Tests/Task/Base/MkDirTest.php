<?php

namespace Himedia\Padocc\Tests\Task\Base;

use GAubry\Shell\ShellAdapter;
use Himedia\Padocc\DIContainer;
use Himedia\Padocc\Properties\Adapter as PropertiesAdapter;
use Himedia\Padocc\Numbering\Adapter as NumberingAdapter;
use Himedia\Padocc\Properties\Adapter;
use Himedia\Padocc\Task\Base\MkDir;
use Himedia\Padocc\Task\Base\Project;
use Himedia\Padocc\Tests\PadoccTestCase;
use Psr\Log\NullLogger;

/**
 * @author Geoffroy AUBRY <gaubry@hi-media.com>
 */
class MkDirTest extends PadoccTestCase
{

    /**
     * Collection de services.
     * @var DIContainer
     */
    private $oDIContainer;

    /**
     * Project.
     * @var Project
     */
    private $oMockProject;

    /**
     * Tableau indexé contenant les commandes Shell de tous les appels effectués à Shell_Adapter::exec().
     * @var array
     * @see shellExecCallback()
     */
    private $aShellExecCmds;

    /**
     * Callback déclenchée sur appel de Shell_Adapter::exec().
     * Log tous les appels dans le tableau indexé $this->aShellExecCmds.
     *
     * @param string $sCmd commande Shell qui aurait dûe être exécutée.
     * @see $aShellExecCmds
     */
   public function shellExecCallback ($sCmd)
    {
        $this->aShellExecCmds[] = $sCmd;
    }

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    public function setUp ()
    {
        $oLogger = new NullLogger();

        /* @var $oMockShell ShellAdapter|\PHPUnit_Framework_MockObject_MockObject */
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($oLogger));
        $oMockShell->expects($this->any())->method('exec')->will($this->returnCallback(array($this, 'shellExecCallback')));
        $this->aShellExecCmds = array();

        $oClass = new \ReflectionClass('\GAubry\Shell\ShellAdapter');
        $oProperty = $oClass->getProperty('_aFileStatus');
        $oProperty->setAccessible(true);
        $oProperty->setValue($oMockShell, array(
            '/path/to/srcdir' => 2
        ));

        $oProperties = new PropertiesAdapter($oMockShell, $this->aConfig);

        $oNumbering = new NumberingAdapter();

        $this->oDIContainer = new DIContainer();
        $this->oDIContainer
            ->setLogger($oLogger)
            ->setPropertiesAdapter($oProperties)
            ->setShellAdapter($oMockShell)
            ->setNumberingAdapter($oNumbering)
            ->setConfig($this->aConfig);

        $this->oMockProject = $this->getMock('\Himedia\Padocc\Task\Base\Project', array(), array(), '', false);
    }

    /**
     * Tears down the fixture, for example, close a network connection.
     * This method is called after a test is executed.
     */
    public function tearDown()
    {
        $this->oDIContainer = null;
        $this->oMockProject = null;
    }

    /**
     * @covers \Himedia\Padocc\Task\Base\MkDir::__construct
     * @covers \Himedia\Padocc\Task\Base\MkDir::check
     */
    public function testCheck_WithoutMode ()
    {
        $oTask = MkDir::getNewInstance(array('destdir' => '/path/to/destdir'), $this->oMockProject, $this->oDIContainer);
        $oTask->setUp();
        $this->assertAttributeEquals(array(
            'destdir' => '/path/to/destdir'
        ), 'aAttValues', $oTask);
    }

    /**
     * @covers \Himedia\Padocc\Task\Base\MkDir::__construct
     * @covers \Himedia\Padocc\Task\Base\MkDir::check
     */
    public function testCheck_WithMode ()
    {
        $oTask = MkDir::getNewInstance(array('destdir' => '/path/to/destdir', 'mode' => '755'), $this->oMockProject, $this->oDIContainer);
        $oTask->setUp();
        $this->assertAttributeEquals(array(
            'destdir' => '/path/to/destdir',
            'mode' => '755'
        ), 'aAttValues', $oTask);
    }

    /**
     * @covers \Himedia\Padocc\Task\Base\MkDir::execute
     * @covers \Himedia\Padocc\Task\Base\MkDir::preExecute
     * @covers \Himedia\Padocc\Task\Base\MkDir::centralExecute
     * @covers \Himedia\Padocc\Task\Base\MkDir::postExecute
     */
    public function testExecute_WithoutMode ()
    {
        /* @var $oMockProperties Adapter|\PHPUnit_Framework_MockObject_MockObject */
        $oMockProperties = $this->getMock(
            '\Himedia\Padocc\Properties\Adapter',
            array('getProperty'),
            array($this->oDIContainer->getShellAdapter(), $this->aConfig)
        );
        $oMockProperties->expects($this->any())->method('getProperty')
            ->with($this->equalTo('with_symlinks'))
            ->will($this->returnValue('false'));
        $oMockProperties->expects($this->exactly(1))->method('getProperty');
        $this->oDIContainer->setPropertiesAdapter($oMockProperties);

        $oTask = MkDir::getNewInstance(array('destdir' => '/path/to/destdir'), $this->oMockProject, $this->oDIContainer);
        $oTask->setUp();
        $oTask->execute();
        $this->assertEquals(array(
            'mkdir -p "/path/to/destdir"'
        ), $this->aShellExecCmds);
    }

    /**
     * @covers \Himedia\Padocc\Task\Base\MkDir::execute
     * @covers \Himedia\Padocc\Task\Base\MkDir::preExecute
     * @covers \Himedia\Padocc\Task\Base\MkDir::centralExecute
     * @covers \Himedia\Padocc\Task\Base\MkDir::postExecute
     */
    public function testExecute_WithMode ()
    {
        /* @var $oMockProperties Adapter|\PHPUnit_Framework_MockObject_MockObject */
        $oMockProperties = $this->getMock(
            '\Himedia\Padocc\Properties\Adapter',
            array('getProperty'),
            array($this->oDIContainer->getShellAdapter(), $this->aConfig)
        );
        $oMockProperties->expects($this->any())->method('getProperty')
            ->with($this->equalTo('with_symlinks'))
            ->will($this->returnValue('false'));
        $oMockProperties->expects($this->exactly(1))->method('getProperty');
        $this->oDIContainer->setPropertiesAdapter($oMockProperties);

        $oTask = MkDir::getNewInstance(array('destdir' => '/path/to/destdir', 'mode' => '755'), $this->oMockProject, $this->oDIContainer);
        $oTask->setUp();
        $oTask->execute();
        $this->assertEquals(array('mkdir -p "/path/to/destdir" && chmod 755 "/path/to/destdir"'), $this->aShellExecCmds);
    }

    /**
     * @covers \Himedia\Padocc\Task\Base\MkDir::execute
     * @covers \Himedia\Padocc\Task\Base\MkDir::preExecute
     * @covers \Himedia\Padocc\Task\Base\MkDir::centralExecute
     * @covers \Himedia\Padocc\Task\Base\MkDir::postExecute
     */
    public function testExecute_WithModeAndSymLinks ()
    {
        /* @var $oMockProperties Adapter|\PHPUnit_Framework_MockObject_MockObject */
        $oMockProperties = $this->getMock(
            '\Himedia\Padocc\Properties\Adapter',
            array('getProperty'),
            array($this->oDIContainer->getShellAdapter(), $this->aConfig)
        );
        $oMockProperties->expects($this->at(0))->method('getProperty')
            ->with($this->equalTo('with_symlinks'))
            ->will($this->returnValue('true'));
        $oMockProperties->expects($this->at(1))->method('getProperty')
            ->with($this->equalTo('basedir'))
            ->will($this->returnValue('/path/to/destdir'));
        $oMockProperties->expects($this->at(2))->method('getProperty')
            ->with($this->equalTo('execution_id'))
            ->will($this->returnValue('12345'));
        $oMockProperties->expects($this->exactly(3))->method('getProperty');
        $this->oDIContainer->setPropertiesAdapter($oMockProperties);

        $oTask = MkDir::getNewInstance(array('destdir' => 'user@server:/path/to/destdir/subdir', 'mode' => '755'), $this->oMockProject, $this->oDIContainer);
        $oTask->setUp();
        $oTask->execute();

        $sSshOptions = $GLOBALS['aConfig']['GAubry\Shell']['ssh_options'];
        $this->assertEquals(array(
            "ssh $sSshOptions -T user@server /bin/bash <<EOF\n"
                . 'mkdir -p "/path/to/destdir_releases/12345/subdir" && chmod 755 "/path/to/destdir_releases/12345/subdir"' . "\n"
                . 'EOF' . "\n"
        ), $this->aShellExecCmds);
    }
}
