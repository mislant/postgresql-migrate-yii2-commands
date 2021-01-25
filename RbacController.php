<?php
/**
 * Roles Based Access Control helper controller
 *
 * Helps manage roles and permissions
 * @see \app\src\infrastructure\enums\rbac\Roles for list of permissions
 * @see \app\src\infrastructure\enums\rbac\Permissions for list of roles
 *
 * available commands:
 *  - rbac/init
 *
 * ## rbac/init:
 *  Initialize tables for RBAC. Creates and attaches
 *  roles and permissions
 */

namespace app\src\commands;

use app\src\infrastructure\enums\rbac\Rules;
use app\src\infrastructure\helpers\ExceptionHelper;
use app\src\infrastructure\enums\rbac\Roles;
use app\src\infrastructure\enums\rbac\Permissions;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\rbac\Permission;
use yii\rbac\Role;
use yii\rbac\Rule;

/**
 * Class RbacController
 * @package app\commands
 */
class RbacController extends Controller
{
    /**
     * Initialize roles and permissions
     *
     * @return int
     */
    public function actionInit()
    {
        $this->run('migrate/up', ['migrationPath' => '@yii/rbac/migrations']);
        $this->stdout('Initializing rbac system' . PHP_EOL, Console::FG_GREEN);
        \Yii::$app->authManager->removeAll();

        try {
            list($roles, $permissions) = $this->createRbacElements();
            $this->attachRbacElements($roles, $permissions);
        } catch (\Exception $e) {
            $this->stdout(ExceptionHelper::processForConsole($e), Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout('Success' . PHP_EOL, Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * Creates role, permissions and rules
     *
     * @return array[] indexed array with [[ Roles::class[] ]] and [[ Permissions::class[] ]]
     * @throws \Exception
     */
    private function createRbacElements(): array
    {
        $auth = \Yii::$app->authManager;

        foreach (Roles::getConstantsByName() as $role) {
            $new_role = $auth->createRole($role);
            $new_role->description = Roles::getLabel($role);
            $auth->add($new_role);
            $roles[$role] = $new_role;
        }

        foreach (Rules::listData() as $permission => $ruleClass) {
            $rules[$permission] = new $ruleClass();
            $auth->add($rules[$permission]);
        }

        foreach (Permissions::getConstantsByName() as $permission) {
            $new_permission = $auth->createPermission($permission);
            $new_permission->description = Permissions::getLabel($permission);
            if (isset($rules[$permission])) {
                /** @var Rule $rule */
                $rule = $rules[$permission];
                $new_permission->ruleName = $rule->name;
            }
            $auth->add($new_permission);
            $permissions[$permission] = $new_permission;
        }

        return [$roles, $permissions];
    }


    /**
     * Attaches permissions to roles
     *
     * @param Role[] $roles
     * @param Permission[] $permissions
     *
     * @throws \yii\base\Exception
     */
    private function attachRbacElements(array $roles, array $permissions): void
    {
        $auth = \Yii::$app->authManager;

        $auth->addChild($permissions[Permissions::MANAGE_OWN_CATALOG], $permissions[Permissions::MANAGE_CATALOG]);

        $user = $roles[Roles::USER];
        $auth->addChild($user, $permissions[Permissions::ADD_CATALOG]);
        $auth->addChild($user, $permissions[Permissions::MANAGE_OWN_CATALOG]);

        $moderator = $roles[Roles::MODERATOR];
        $auth->addChild($moderator, $user);
        $auth->addChild($moderator, $permissions[Permissions::MANAGE_CATALOG]);
        $auth->addChild($moderator, $permissions[Permissions::MODERATE_CATALOG]);
        $auth->addChild($moderator, $permissions[Permissions::BAN_USER]);

        $admin = $roles[Roles::ADMIN];
        $auth->addChild($admin, $moderator);
        $auth->addChild($admin, $permissions[Permissions::GRANT_ROLE]);
        $auth->addChild($admin, $permissions[Permissions::CREATE_TAG]);
    }
}