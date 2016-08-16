PixelartRolesConfig
===================

A quick shopware plugin to define backend user acl roles within YAML config
files and import them on e.g. deployments.

Installation
------------

```bash
composer require pixelart/shopware-roles-config
```

It is recommended that you add
`engine/Shopware/Plugins/Local/Backend/PixelartRolesConfig` to your
`.gitignore` file if you ignore the composer `vendor` dir too.

Usage
-----

First you need to export all roles to config files. At the current state
only all roles are exported and imported at once. For example you can store
your roles in `.misc/roles`:

```bash
php bin/console pixelart:roles:export .misc/roles/
```

Then you should get one file per backend role. Take care, the filename is
idempotent, which means you should never rename it. Also the name of the
role in the backend is slugified and not allowed to renamed anymore.

Now you can commit your roles into your VCS and change it as you need it.
After changes you can import them with:

```bash
php bin/console pixelart:roles:import .misc/roles/
```

License
-------

The MIT License (MIT). Please see the [LICENSE file](LICENSE) for more
information.
