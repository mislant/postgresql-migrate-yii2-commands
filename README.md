# Base commands that helps to migrate self written sql code for PostgreSQL DB

There are to main files in repositroty:
  - DbContoller (helps to migrate main scripts)
  - RbacController (helps to up RBAC elemnts based on role, permissions, and rules enums)
  
## DbContoller

Contoller has __three__ main commands.

### init:

Init parses yii2 base db config to get dsn needed for making PDO connections. Then it asks you for default password and db user. That user has to be superuser.
All this date are needed to make database and special user that has permission to manage this data base. Data for new user is also taken from db config

### migrate:

Migrate comand takes sql files. __(It is very important for files to contain only one sql command)__. After that script executes each file and than wtites it in one scheme file.

### up:

This command just runs preveous two commands.

## RbacController.

Controller has only __one__ command.

### init:

This command initializes rbac elements that contains in special enum files. It code uses [yii2-baseEnum](https://github.com/yii2mod/yii2-enum) enum realization. You can change it if you want. But dont forget to change code.

Example of enum file:

```php

<?php
/**
 * Enum of roles
 */

namespace app\src\infrastructure\enums\rbac;

use yii2mod\enum\helpers\BaseEnum;

/**
 * Class Roles
 * @package app\src\enums\rbac
 */
class Roles extends BaseEnum
{
    /** Administrator is maintainer of site */
    const ADMIN = 'rAdministrator';
    /** Moderator is content manager of hentai site */
    const MODERATOR = 'rModerator';
    /** Authorized user */
    const USER = 'rUser';

    /**
     * {@inheritDoc}
     */
    protected static $list = [
        self::ADMIN => 'Администратор',
        self::MODERATOR => 'Модератор',
        self::USER => 'Пользователь',
    ];
}

```

__But__ there is some difference in rule enum file. Rules in yii2 is separated class with special permission logic. So file should look like this:


```php

<?php
/**
 * Enum of rules
 */

namespace app\src\infrastructure\enums\rbac;

use app\src\infrastructure\rules\AuthorRule;
use yii2mod\enum\helpers\BaseEnum;

/**
 * Class Rules
 * @package app\src\enums\rbac
 */
class Rules extends BaseEnum
{
    /**
     * Check author of catalog
     *
     * @see AuthorRule
     */
    const IS_AUTHOR = AuthorRule::class;

    /**
     * Links permission to rule
     *
     * {@inheritDoc}
     */
    protected static $list = [
        Permissions::MANAGE_OWN_CATALOG => self::IS_AUTHOR,
    ];
}

```

And that is example of rule class:

```php

/**
 * Class AuthorRule
 * @package app\src\rules
 */
class AuthorRule extends Rule
{
    /**
     * @var string $name
     * {@inheritDoc}
     */
    public $name = 'Is author';

    /**
     * {@inheritDoc}
     */
    public function execute($user, $item, $params): bool
    {
        if (!isset($params['catalog'])) {
            throw new \Exception('Parameter \'catalog\' for verification was not passed');
        }
        $catalog = $params['catalog'];
        return $catalog->author === $user;
    }
}

```

# That's all. You should remember that is just template. It can has diffrence for each project.
