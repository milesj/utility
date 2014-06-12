<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

Configure::write('debug', 2);
Configure::write('Cache.disable', true);

App::uses('ConnectionManager', 'Model');
App::uses('Validation', 'Utility');
App::uses('AppShell', 'Console/Command');
config('database');

abstract class BaseInstallShell extends AppShell {

    /**
     * Runtime configuration.
     *
     * @type array
     */
    public $config = array();

    /**
     * DB Instance.
     *
     * @type DboSource
     */
    public $db;

    /**
     * Database config to use.
     *
     * @type string
     */
    public $dbConfig;

    /**
     * Tables required for installation to proceed.
     *
     * @type array
     */
    public $requiredTables = array();

    /**
     * Steps to run through.
     *
     * @type array
     */
    public $steps = array();

    /**
     * Table prefix.
     *
     * @type string
     */
    public $tablePrefix;

    /**
     * Single user record.
     *
     * @type array
     */
    public $user;

    /**
     * Mapping of user fields to grab during user creation.
     *
     * @type array
     */
    public $userFields = array(
        'username' => 'username',
        'password' => 'password',
        'email' => 'email'
    );

    /**
     * Users model.
     *
     * @type string
     */
    public $usersModel = 'User';

    /**
     * Users database table.
     *
     * @type string
     */
    public $usersTable = 'users';

    /**
     * Execute installer and cycle through all steps.
     */
    public function main() {
        $this->stdout->styles('success', array('text' => 'green'));

        $this->hr(1);
        $this->out(sprintf('%s Steps:', $this->name));

        $counter = 1;

        foreach ($this->steps as $method) {
            $this->steps($counter);

            try {
                if (!call_user_func(array($this, $method))) {
                    $this->err('<error>Process aborted!</error>');
                    break;
                }
            } catch (Exception $e) {
                $this->err(sprintf('<error>Unexpected error has occurred; %s</error>', $e->getMessage()));
                break;
            }

            $counter++;
        }
    }

    /**
     * Check the database status before installation.
     *
     * @return bool
     */
    public function checkDbConfig() {
        if (!$this->dbConfig || !$this->db) {
            $dbConfigs = array_keys(get_class_vars('DATABASE_CONFIG'));

            $this->out('Available database configurations:');

            foreach ($dbConfigs as $i => $db) {
                $this->out(sprintf('[%s] <info>%s</info>', $i, $db));
            }

            $this->out();

            $answer = $this->in('Which database should the queries be executed in?', array_keys($dbConfigs));

            if (isset($dbConfigs[$answer])) {
                $this->setDbConfig($dbConfigs[$answer]);
            } else {
                $this->checkDbConfig();
            }
        }

        $this->out(sprintf('Database Config: <info>%s</info>', $this->dbConfig));

        $answer = strtoupper($this->in('Is this correct?', array('Y', 'N')));

        if ($answer === 'N') {
            $this->dbConfig = null;
            $this->checkDbConfig();
        }

        // Check that database is connected
        if (!$this->db->isConnected()) {
            $this->err(sprintf('<error>Database connection for %s failed</error>', $this->dbConfig));
            return false;
        }

        $this->out('<success>Database check successful, proceeding...</success>');
        return true;
    }

    /**
     * Check that all the required tables exist in the database.
     *
     * @return bool
     */
    public function checkRequiredTables() {
        if ($this->requiredTables) {
            $this->out(sprintf('The following tables are required: <info>%s</info>', implode(', ', $this->requiredTables)));
            $this->out('<success>Checking tables...</success>');

            $tables = $this->db->listSources();
            $missing = array();

            foreach ($this->requiredTables as $table) {
                if (!in_array($this->db->config['prefix'] . $table, $tables)) {
                    $missing[] = $table;
                }
            }

            if ($missing) {
                $this->err(sprintf('<error>Missing tables %s; can not proceed</error>', implode(', ', $missing)));
                return false;
            }
        }

        $this->out('<success>Table status good, proceeding...</success>');
        return true;
    }

    /**
    * Set the table prefix to use.
    *
    * @return bool
    */
    public function checkTablePrefix() {
        if (!$this->tablePrefix) {
            $this->setTablePrefix($this->in('What table prefix would you like to use?'));
        }

        $this->out(sprintf('Table Prefix: <info>%s</info>', $this->tablePrefix));

        $answer = strtoupper($this->in('Is this correct?', array('Y', 'N')));

        if ($answer === 'N') {
            $this->tablePrefix = null;
            $this->checkTablePrefix();
        }

        $this->out('<success>Table prefix set, proceeding...</success>');
        return true;
    }

    /**
     * Determine the users model to use.
     *
     * @return bool
     */
    public function checkUsersModel() {
        if (!$this->usersModel) {
            $this->setUsersModel($this->in('What is the name of your users model?'));
        }

        $this->out(sprintf('Users Model: <info>%s</info>', $this->usersModel));

        $answer = strtoupper($this->in('Is this correct?', array('Y', 'N')));

        if ($answer === 'N') {
            $this->usersModel = null;
            $this->checkUsersModel();
        }
    }

    /**
     * Determine the users table to use.
     *
     * @return bool
     */
    public function checkUsersTable() {
        if (!$this->usersTable) {
            $this->setUsersTable($this->in('What is the name of your users table?'));
        }

        $this->out(sprintf('Users Table: <info>%s</info>', $this->usersTable));

        $answer = strtoupper($this->in('Is this correct?', array('Y', 'N')));

        if ($answer === 'N') {
            $this->usersTable = null;
            $this->checkUsersTable();
        } else {
            $this->setRequiredTables(array($this->usersTable));
        }

        $this->checkUsersModel();

        $this->out('<success>Users table set, proceeding...</success>');
        return true;
    }

    /**
     * Create the database tables based off the schemas.
     *
     * @return bool
     */
    public function createTables() {
        $answer = strtoupper($this->in('Existing tables will be deleted, continue?', array('Y', 'N')));

        if ($answer === 'N') {
            return false;
        }

        $schemas = glob(CakePlugin::path($this->plugin) . '/Config/Schema/*.sql');
        $executed = 0;
        $tables = array();

        // Loop over schemas and execute queries
        $this->out('<success>Creating tables...</success>');

        foreach ($schemas as $schema) {
            $table = $this->tablePrefix . str_replace('.sql', '', basename($schema));
            $tables[] = $table;

            $executed += $this->executeSchema($schema);
            $this->out($table);
        }

        // Rollback if a failure occurs
        if ($executed != count($schemas)) {
            $this->out('<error>Failed to create database tables; rolling back</error>');

            foreach ($tables as $table) {
                $this->db->execute(sprintf('DROP TABLE `%s`;', $table));
            }

            return false;
        }

        $this->out('<success>Tables created successfully, proceeding...</success>');
        return true;
    }

    /**
     * Gather all the data for creating a new user.
     *
     * @return int
     */
    public function createUser() {
        $data = array();

        foreach ($this->userFields as $base => $field) {
            $data[$field] = $this->getFieldInput($base);
        }

        $model = ClassRegistry::init($this->usersModel);
        $model->create();
        $model->save($data, false);

        $this->config = $data;
        $this->user = $model->find('first', array(
            'conditions' => array($model->alias . '.' . $model->primaryKey => $model->id),
            'recursive' => -1
        ));

        return $model->id;
    }

    /**
     * Execute a schema SQL file using the loaded datasource.
     *
     * @param string $path
     * @param bool $track
     * @return int
     * @throws DomainException
     */
    public function executeSchema($path, $track = true) {
        if (!file_exists($path)) {
            throw new DomainException(sprintf('<error>Schema %s does not exist</error>', basename($path)));
        }

        $contents = file_get_contents($path);
        $contents = String::insert($contents, array('prefix' => $this->tablePrefix), array('before' => '{', 'after' => '}'));
        $contents = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $contents);

        $queries = explode(';', $contents);
        $executed = 0;

        foreach ($queries as $query) {
            $query = trim($query);

            if (!$query) {
                continue;
            }

            if ($this->db->execute($query)) {
                $command = trim(substr($query, 0, strpos($query, ' ')));

                if ($track) {
                    if ($command === 'CREATE' || $command === 'ALTER') {
                        $executed++;
                    }
                } else {
                    $executed++;
                }
            }
        }

        return $executed;
    }

    /**
     * Find a user within the users table.
     *
     * @return int
     */
    public function findUser() {
        $model = ClassRegistry::init($this->usersModel);
        $id = trim($this->in('User ID:'));

        if (!$id) {
            $this->out('<error>Invalid ID, please try again</error>');
            return $this->findUser();
        }

        $result = $model->find('first', array(
            'conditions' => array($model->alias . '.' . $model->primaryKey => $id),
            'recursive' => -1
        ));

        if (!$result) {
            $this->out('<error>User does not exist, please try again</error>');
            return $this->findUser();
        }

        $this->user = $result;

        return $id;
    }

    /**
     * Get the value of an input.
     *
     * @param string $field
     * @return string
     */
    public function getFieldInput($field) {
        $model = ClassRegistry::init($this->usersModel);

        switch ($field) {
            case 'username':
                $username = trim($this->in('Username:'));

                if (!$username) {
                    $username = $this->getFieldInput($field);

                } else {
                    $result = $model->find('count', array(
                        'conditions' => array($model->alias . '.' . $this->userFields['username'] => $username)
                    ));

                    if ($result) {
                        $this->out('<error>Username already exists, please try again</error>');
                        $username = $this->getFieldInput($field);
                    }
                }

                return $username;
            break;

            case 'email':
                $email = trim($this->in('Email:'));

                if (!$email) {
                    $email = $this->getFieldInput($field);

                } else if (!Validation::email($email)) {
                    $this->out('<error>Invalid email address, please try again</error>');
                    $email = $this->getFieldInput($field);

                } else {
                    $result = $model->find('count', array(
                        'conditions' => array($model->alias . '.' . $this->userFields['email'] => $email)
                    ));

                    if ($result) {
                        $this->out('<error>Email already exists, please try again</error>');
                        $email = $this->getFieldInput($field);
                    }
                }

                return $email;
            break;

            // Password, others...
            default:
                $value = trim($this->in(sprintf('%s:', Inflector::humanize($field))));

                if (!$value) {
                    $value = $this->getFieldInput($field);
                }

                return $value;
            break;
        }
    }

    /**
     * Set the database config to use and load the data source.
     *
     * @param string $config
     * @return BaseInstallShell
     */
    public function setDbConfig($config) {
        $this->dbConfig = $config;

        $this->db = ConnectionManager::getDataSource($config);
        $this->db->cacheSources = false;

        return $this;
    }

    /**
     * Set the required tables.
     *
     * @param array $tables
     * @return BaseInstallShell
     */
    public function setRequiredTables(array $tables) {
        $this->requiredTables = array_unique(array_merge($this->requiredTables, $tables));

        return $this;
    }

    /**
     * Set the list of steps to trigger during install.
     * Step key is the step name and the value is the method to execute.
     *
     * @param array $steps
     * @return BaseInstallShell
     */
    public function setSteps(array $steps) {
        $this->steps = $steps;

        return $this;
    }

    /**
     * Set the table prefix to use.
     *
     * @param string $prefix
     * @return BaseInstallShell
     */
    public function setTablePrefix($prefix) {
        if ($prefix) {
            $prefix = trim($prefix, '_') . '_';
        }

        $this->tablePrefix = $prefix;

        return $this;
    }

    /**
     * Set the user fields mapping.
     *
     * @param array $fields
     * @return BaseInstallShell
     */
    public function setUserFields(array $fields) {
        $clean = array();

        foreach ($fields as $base => $field) {
            if (is_numeric($base)) {
                $base = $field;
            }

            $clean[$base] = $field;
        }

        $this->userFields = $clean;

        return $this;
    }

    /**
     * Set the users model.
     *
     * @param string $model
     * @return BaseInstallShell
     */
    public function setUsersModel($model) {
        $this->usersModel = trim($model);

        return $this;
    }

    /**
     * Set the users table.
     *
     * @param string $table
     * @return BaseInstallShell
     */
    public function setUsersTable($table) {
        $this->usersTable = trim($table);

        return $this;
    }

    /**
     * Table of contents.
     *
     * @param int $step
     */
    public function steps($step = 0) {
        $this->hr(1);

        $counter = 1;

        foreach ($this->steps as $title => $method) {
            if ($counter < $step) {
                $this->out('[x] ' . $title);
            } else {
                $this->out(sprintf('[%s] <info>%s</info>', $counter, $title));
            }

            $counter++;
        }

        $this->out();
    }

}