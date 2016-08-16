<?php

/*
 * This file is part of pixelart roles config plugin.
 *
 * (c) pixelart GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Shopware\Plugins\PixelartRolesConfig\Commands;

use Shopware\Commands\ShopwareCommand;
use Shopware\Models\User\Role;
use Shopware\Models\User\Rule;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Patrik Karisch <p.karisch@pixelart.at>
 */
class ExportRolesCommand extends ShopwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pixelart:roles:export')
            ->setDescription('Export the roles and acl into config files')
            ->addArgument('path', InputArgument::REQUIRED, 'The path to export the roles to')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = rtrim($input->getArgument('path'), '/\\');
        $filesystem = $this->getContainer()->get('file_system');
        $em = $this->getContainer()->get('models');

        $qb = $em->getRepository('Shopware\Models\User\Role')->createQueryBuilder('role');
        $qb->select('role', 'rule', 'resource', 'privilege')
            ->innerJoin('role.rules', 'rule')
            ->leftJoin('rule.resource', 'resource')
            ->leftJoin('rule.privilege', 'privilege')
        ;

        /** @var Role[] $roles */
        $roles = $qb->getQuery()->getResult();

        foreach ($roles as $role) {
            $slug = strtolower(str_replace(' ', '_', $role->getName()));
            if ($role->getName() !== $slug) {
                $role->setName($slug);
                $em->persist($role);
            }

            $result = [
                'description' => $role->getDescription(),
                'rules' => [],
            ];

            $resourcesToOmit = [];

            /** @var Rule $rule */
            foreach ($role->getRules() as $rule) {
                $resource = $rule->getResource();
                $privilege = $rule->getPrivilege();

                if (null === $resource && null === $privilege) {
                    $result['rules'] = null;

                    break;
                }

                $resourceName = $resource->getName();

                if (null === $privilege) {
                    $resourcesToOmit[$resourceName] = true;
                    $result['rules'][$resourceName] = null;
                } elseif (!isset($resourcesToOmit[$resourceName])) {
                    $result['rules'][$resourceName][] = $privilege->getName();
                }
            }

            $filesystem->dumpFile(
                $path.DIRECTORY_SEPARATOR.$role->getName().'.yml',
                str_replace('null', '~', Yaml::dump($result, 3))
            );
        }

        $em->flush();
    }
}
