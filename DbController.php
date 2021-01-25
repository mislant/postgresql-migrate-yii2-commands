<?php
/**
 * Data base console commands
 *
 * available commands:
 *  - db/init
 *  - db/migrate
 *
 * ## db/init:
 *  Initializes PostgreSQL 'manga' database and 'webuser' role for
 *  database management from web
 *
 * ## db/migrate
 *  Executes table migrations for database and writes DDL Schema file
 *
 * ## db/up
 *  Runs all initial scripts
 *
 */

namespace app\src\commands;

use app\src\infrastructure\helpers\ExceptionHelper;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Connection;
use yii\helpers\Console;

/**
 * Class DataController
 * @package app\commands
 */
class DbController extends Controller
{

    /**
     * Starts up full db
     *
     * Runs all db controller's actions.
     *
     * @return int
     */
    public function actionUp()
    {
        $actions = [
            'init',
            'migrate',
        ];
        foreach ($actions as $action) {
            $exCode = $this->run($action);
            if (!$exCode === ExitCode::OK) {
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }
        return ExitCode::OK;
    }

    /**
     * Creates database and database user for web application.
     *
     * @return int
     */
    public function actionInit()
    {
        $this->outLine();
        $this->stdout("PostgreSQL web application data base initialization started" . PHP_EOL, Console::FG_GREEN);
        if ($this->checkPDO()) {
            try {
                $connection = $this->defaultConnect();
                $this->initDataBase($connection);
            } catch (\Exception $e) {
                $this->outError($e);
                return ExitCode::UNSPECIFIED_ERROR;
            }
        } else {
            $this->stdout('Please install PDO extension' . PHP_EOL, Console::FG_YELLOW);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $this->stdout("PostgreSQL web application data base initialization finished" . PHP_EOL, Console::FG_GREEN);
        $this->outLine();
        return ExitCode::OK;
    }

    /**
     * Checks data base drivers
     *
     * @return bool
     */
    private function checkPDO(): bool
    {
        return extension_loaded('PDO') && in_array('pgsql', pdo_drivers());
    }

    /**
     * Connects to database as superuser
     *
     * Parses database settings such host and port from
     * configuration file and creates user for db management
     *
     * @return Connection
     * @throws \yii\db\Exception
     */
    private function defaultConnect(): Connection
    {
        $connection = new Connection();
        $connection->username = $this->prompt('PostgreSQL superuser:', ['required' => true]);
        $connection->password = $this->prompt('Password:');
        $connection->dsn = 'pgsql:';
        preg_match('/host=((localhost)|((?:[0-9]{1,3}\.){3}[0-9]{1,3}));port=[0-9]*/', \Yii::$app->db->dsn, $host);
        $connection->dsn .= $host[0];
        $connection->open();
        $this->stdout('Data base is connected' . PHP_EOL, Console::FG_GREEN);
        return $connection;
    }

    /**
     * Creates database user and database
     *
     * @param Connection $connection
     *
     * @throws \yii\db\Exception
     */
    private function initDataBase(Connection $connection): void
    {
        $username = \Yii::$app->db->username;
        $password = \Yii::$app->db->password;
        if (!$connection
            ->createCommand("SELECT rolname FROM pg_catalog.pg_roles WHERE rolname = '$username'")
            ->queryOne()
        ) {
            $connection
                ->createCommand("CREATE ROLE $username WITH CREATEDB CREATEROLE LOGIN PASSWORD '$password'")
                ->execute();
            $this->stdout('Web application database user created' . PHP_EOL);
        }

        $db = 'manga';
        if ($connection
            ->createCommand("SELECT datname FROM pg_catalog.pg_database WHERE datname = '$db'")
            ->queryOne()
        ) {
            $connection->createCommand("DROP DATABASE $db")->execute();
        }
        $connection->createCommand("CREATE DATABASE $db OWNER $username ENCODING 'UTF-8'")->execute();
        $this->stdout('Web application database created' . PHP_EOL);
    }

    /**
     * Migrate database
     */
    public function actionMigrate()
    {
        $this->outLine();
        try {
            $this->stdout('Migrations initialization' . PHP_EOL, Console::FG_GREEN);
            $migrations = $this->migrate();
            $this->writeMigrate($migrations);
            $this->stdout('Migration finished' . PHP_EOL, Console::FG_GREEN);
        } catch (\Exception $e) {
            $this->outError($e);
        }
        $this->outLine();
        return ExitCode::OK;
    }

    /**
     * Executes migrations
     *
     * @return array|false
     * @throws \Throwable
     * @throws \yii\db\Exception
     */
    private function migrate()
    {
        $db = \Yii::$app->db;
        $db->open();
        $migrations = array_diff(scandir($dir = \Yii::getAlias('@app') . '/src/database/migrations'), ['.', '..']);
        foreach ($migrations as $index => $migration) {
            $migrations[$migration] = file_get_contents($dir . '/' . $migration);
            unset($migrations[$index]);
        }
        $db->transaction(function (Connection $db) use ($migrations) {
            foreach ($migrations as $file => $migration) {
                $db->createCommand($migration)->execute();
                $this->stdout("$file is migrated \n", Console::FG_GREEN);
            }
        });
        $db->close();
        return $migrations;
    }

    /**
     * Writes table schema of migrations
     *
     * @param array $migrations
     */
    private function writeMigrate(array $migrations)
    {
        $file = fopen(\Yii::getAlias('@app') . '/src/database/_migration.sql', 'w+');
        fwrite($file, "/* Autogenerated table schema of database */ \n\n");
        foreach ($migrations as $migration) {
            fwrite($file, $migration);
            fwrite($file, "\n\n");
        }
        fclose($file);
    }

    /**
     * Outs handled exception
     *
     * @param \Exception $e
     */
    private function outError(\Exception $e): void
    {
        $this->outLine();
        $this->stdout(ExceptionHelper::processForConsole($e), Console::FG_RED);
        $this->outLine();
    }

    /**
     * Out dividing line
     */
    private function outLine(): void
    {
        $this->stdout(str_repeat('-', 50) . PHP_EOL);
    }
}
