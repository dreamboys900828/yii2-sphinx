<?php

namespace yiiunit\extensions\sphinx;

use yii\helpers\ArrayHelper;
use yii\sphinx\Connection;
use Yii;

/**
 * Base class for the Sphinx test cases.
 */
class TestCase extends \PHPUnit\Framework\TestCase
{
    public static $params;

    /**
     * @var array Sphinx connection configuration.
     */
    protected $sphinxConfig = [
        'dsn' => 'mysql:host=127.0.0.1;port=9306;',
        'username' => '',
        'password' => '',
    ];
    /**
     * @var Connection Sphinx connection instance.
     */
    protected $sphinx;
    /**
     * @var array Database connection configuration.
     */
    protected $dbConfig = [
        'dsn' => 'mysql:host=127.0.0.1;',
        'username' => '',
        'password' => '',
    ];
    /**
     * @var \yii\db\Connection database connection instance.
     */
    protected $db;

    protected function setUp()
    {
        parent::setUp();
        if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
            $this->markTestSkipped('pdo and pdo_mysql extension are required.');
        }
        $this->sphinxConfig = self::getParam('sphinx');
        $this->dbConfig = self::getParam('db');
        // check whether sphinx is running and skip tests if not.
        if (preg_match('/host=([\w\d.]+)/i', $this->sphinxConfig['dsn'], $hm) && preg_match('/port=(\d+)/i', $this->sphinxConfig['dsn'], $pm)) {
            if (!@stream_socket_client($hm[1] . ':' . $pm[1], $errorNumber, $errorDescription, 0.5)) {
                $this->markTestSkipped('No Sphinx searchd running at ' . $hm[1] . ':' . $pm[1] . ' : ' . $errorNumber . ' - ' . $errorDescription);
            }
        }
        $this->mockApplication();
    }

    protected function tearDown()
    {
        if ($this->sphinx) {
            $this->sphinx->close();
        }
        $this->destroyApplication();
    }

    /**
     * Returns a test configuration param from /data/config.php
     * @param  string $name params name
     * @param  mixed $default default value to use when param is not set.
     * @return mixed  the value of the configuration param
     */
    public static function getParam($name, $default = null)
    {
        if (static::$params === null) {
            static::$params = require(__DIR__ . '/data/config.php');
        }

        return isset(static::$params[$name]) ? static::$params[$name] : $default;
    }

    /**
     * Populates Yii::$app with a new application
     * The application will be destroyed on tearDown() automatically.
     * @param array $config The application configuration, if needed
     * @param string $appClass name of the application class to create
     */
    protected function mockApplication($config = [], $appClass = '\yii\console\Application')
    {
        new $appClass(ArrayHelper::merge([
            'id' => 'testapp',
            'basePath' => __DIR__,
            'vendorPath' => $this->getVendorPath(),
        ], $config));
    }

    protected function getVendorPath()
    {
        $vendor = dirname(dirname(__DIR__)) . '/vendor';
        if (!is_dir($vendor)) {
            $vendor = dirname(dirname(dirname(dirname(__DIR__))));
        }
        return $vendor;
    }

    /**
     * Destroys application in Yii::$app by setting it to null.
     */
    protected function destroyApplication()
    {
        \Yii::$app = null;
    }

    /**
     * @param bool $reset whether to clean up the test database
     * @param bool $open whether to open test database
     * @return \yii\sphinx\Connection
     */
    public function getConnection($reset = false, $open = true)
    {
        if (!$reset && $this->sphinx) {
            return $this->sphinx;
        }
        $db = new Connection;
        $db->dsn = $this->sphinxConfig['dsn'];
        if (isset($this->sphinxConfig['username'])) {
            $db->username = $this->sphinxConfig['username'];
            $db->password = $this->sphinxConfig['password'];
        }
        if (isset($this->sphinxConfig['attributes'])) {
            $db->attributes = $this->sphinxConfig['attributes'];
        }
        if ($open) {
            $db->open();
        }
        $this->sphinx = $db;

        return $db;
    }

    /**
     * Truncates the runtime index.
     * @param string $indexName index name.
     */
    protected function truncateIndex($indexName)
    {
        if ($this->sphinx) {
            $this->sphinx->createCommand('TRUNCATE RTINDEX ' . $indexName)->execute();
        }
    }

    /**
     * @param bool $reset whether to clean up the test database
     * @param bool $open whether to open and populate test database
     * @return \yii\db\Connection
     */
    public function getDbConnection($reset = true, $open = true)
    {
        if (!$reset && $this->db) {
            return $this->db;
        }
        $db = new \yii\db\Connection;
        $db->dsn = $this->dbConfig['dsn'];
        if (isset($this->dbConfig['username'])) {
            $db->username = $this->dbConfig['username'];
            $db->password = $this->dbConfig['password'];
        }
        if (isset($this->dbConfig['attributes'])) {
            $db->attributes = $this->dbConfig['attributes'];
        }
        if ($open) {
            $db->open();
            if (!empty($this->dbConfig['fixture'])) {
                $lines = explode(';', file_get_contents($this->dbConfig['fixture']));
                foreach ($lines as $line) {
                    if (trim($line) !== '') {
                        $db->pdo->exec($line);
                    }
                }
            }
        }
        $this->db = $db;

        return $db;
    }
}
