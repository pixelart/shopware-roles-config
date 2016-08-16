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
use Shopware\Components\Model\ModelManager;
use Shopware\Models\User\Privilege;
use Shopware\Models\User\Resource;
use Shopware\Models\User\Role;
use Shopware\Models\User\Rule;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Patrik Karisch <p.karisch@pixelart.at>
 */
class ImportRolesCommand extends ShopwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('pixelart:roles:import')
            ->setDescription('Import the roles and acl from config files')
            ->addArgument('path', InputArgument::REQUIRED, 'The path to import the roles from')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = rtrim($input->getArgument('path'), '/\\');
        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $em = $this->getContainer()->get('models');

        $qb = $em->createQueryBuilder();
        $qb->select('resource', 'privilege')
            ->from('Shopware\Models\User\Resource', 'resource', 'resource.name')
            ->innerJoin('resource.privileges', 'privilege', null, null, 'privilege.name')
        ;

        /** @var \Shopware\Models\User\Resource[] $resources */
        $resources = $qb->getQuery()->getResult();

        $finder = new Finder();
        $finder->files()
            ->ignoreDotFiles(true)
            ->ignoreVCS(true)
            ->in($path)
            ->name('*.yml')
        ;

        $roles = [];

        /** @var SplFileInfo $roleFile */
        foreach ($finder as $roleFile) {
            $roleData = Yaml::parse($roleFile->getContents());
            if (!array_key_exists('description', $roleData) || !array_key_exists('rules', $roleData)) {
                throw new \DomainException('Malformed roles file '.$roleFile->getFilename());
            }

            $roleName = $roleFile->getBasename('.yml');

            /** @var Role $role */
            $role = $em->getRepository('Shopware\Models\User\Role')->findOneBy([
                'name' => $roleName,
            ])
            ;

            if (null === $role) {
                $role = new Role();
                $role->setName($roleName);
                $role->setSource('custom');
                $role->setEnabled(1);
                $role->setAdmin(0);
            }

            $role->setDescription($roleData['description']);

            $roles[$roleName] = [
                'role' => $role,
                'rules' => $roleData['rules'],
            ];

            $em->persist($role);
        }

        $em->flush();

        // reorder roles after ids..
        uasort($roles, function($a, $b) {
            $idA = (int) $a['role']->getId();
            $idB = (int) $b['role']->getId();

            if ($idA === $idB) {
                return 0;
            }

            return ($idA < $idB) ? -1 : 1;
        });


        foreach ($roles as $roleName => $roleData) {
            $role = $roleData['role'];

            if (null === $roleData['rules']) {
                $rule = new Rule();
                $rule->setRole($role);

                $em->persist($rule);
                continue;
            }

            foreach ($roleData['rules'] as $resourceName => $privilegeNames) {
                if (!array_key_exists($resourceName, $resources)) {
                    $errOutput->writeln(sprintf(
                        '<comment>Resource %s not registered in system. Ignoring!</comment>',
                        $resourceName
                    ));

                    continue;
                }

                $resource = $resources[$resourceName];

                /** @var Privilege[] $privileges */
                $privileges = $resource->getPrivileges()->toArray();

                if (null === $privilegeNames) {
                    $rule = new Rule();
                    $rule->setResource($resource);
                    $rule->setRole($role);
                    $em->persist($rule);

                    foreach ($privileges as $privilege) {
                        $rule = new Rule();
                        $rule->setResource($resource);
                        $rule->setPrivilege($privilege);
                        $rule->setRole($role);
                        $em->persist($rule);
                    }
                } else {
                    foreach ($privilegeNames as $privilegeName) {
                        if (!array_key_exists($privilegeName, $privileges)) {
                            $errOutput->writeln(sprintf(
                                '<comment>Privilege %s for resource %s not registered in system. Ignoring!</comment>',
                                $privilegeName,
                                $resourceName
                            ));

                            continue;
                        }

                        $rule = new Rule();
                        $rule->setResource($resource);
                        $rule->setPrivilege($privileges[$privilegeName]);
                        $rule->setRole($role);
                        $em->persist($rule);
                    }
                }
            }
        }

        $em->transactional(function ($em) {
            /** @var ModelManager $em */
            $em->getConnection()->exec('TRUNCATE TABLE s_core_acl_roles');
        });
    }
}
