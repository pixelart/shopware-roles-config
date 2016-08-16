<?php

/*
 * This file is part of pixelart roles config plugin.
 *
 * (c) pixelart GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\Common\Collections\ArrayCollection;
use Shopware\Plugins\PixelartRolesConfig\Commands\ExportRolesCommand;
use Symfony\Component\Console\Command\Command;

/**
 * @author Patrik Karisch <p.karisch@pixelart.at>
 */
class Shopware_Plugins_Backend_PixelartRolesConfig_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * @var array
     */
    private $pluginInfo;

    /**
     * {@inheritDoc}
     */
    public function getLabel()
    {
        return $this->getPluginInfo()['label']['de'];
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion()
    {
        return $this->getPluginInfo()['currentVersion'];
    }

    /**
     * {@inheritDoc}
     */
    public function getInfo()
    {
        return [
            'version' => $this->getVersion(),
            'label' => $this->getLabel(),
            'description' => 'Fügt zwei CLI-Befehle hinzu um die Rollen aus Konfigurationsdateien zu importieren/exportieren.',
            'supplier' => $this->getPluginInfo()['author'],
            'link' => 'https://www.pixelart.at',
            'author' => $this->getPluginInfo()['author'],
            'source' => $this->getPluginInfo()['link'],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function install()
    {
        $requiredVersion = $this->getPluginInfo()['compatibility']['minimumVersion'];
        if (!$this->assertMinimumVersion($requiredVersion)) {
            throw new DomainException('This plugin requires Shopware '.$requiredVersion.' or a later version');
        }

        $this->subscribeEvent(
            'Shopware_Console_Add_Command',
            'onAddConsoleCommand'
        );

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function update($version)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function afterInit()
    {
        $this->Application()->Loader()->registerNamespace(
            'Shopware\Plugins\PixelartRolesConfig',
            $this->Path()
        );
    }

    /**
     * @return Command[]
     */
    public function onAddConsoleCommand()
    {
        return new ArrayCollection([
            new ExportRolesCommand(),
        ]);
    }

    /**
     * @return array
     */
    private function getPluginInfo()
    {
        if (null === $this->pluginInfo) {
            $this->pluginInfo = json_decode(file_get_contents(__DIR__.DIRECTORY_SEPARATOR.'plugin.json'), true);

            if (!$this->pluginInfo) {
                throw new DomainException('The plugin has an invalid plugin.json file');
            }
        }

        return $this->pluginInfo;
    }
}
