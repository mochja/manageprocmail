<?php

use League\Flysystem;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter;


/**
 * Class Ingo_Transport_Flysystem
 * @author Ján Mochňak <janmochnak@gmail.com>
 */
class Ingo_Transport_Flysystem extends Ingo_Transport_Base
{

    /**
     * @var Filesystem
     */
    private $_vfs;

    /**
     * Constructs a new VFS-based storage driver.
     *
     * @param array $params  A hash containing driver parameters.
     */
    public function __construct(array $params = array())
    {
        $default_params = array(
            'host' => 'localhost',
            'port'     => 21,
            'filename' => '.ingo_filter',
            'vfstype'  => 'ftp',
            'root' => '',

            'visibility' => 'private',
        );

        $this->_supportShares = true;

        parent::__construct(array_merge($default_params, $params));
    }

    /**
     * Sets a script running on the backend.
     *
     * @param array $script  The filter script information. Passed elements:
     *                       - 'name': (string) the script name.
     *                       - 'recipes': (array) the filter recipe objects.
     *                       - 'script': (string) the filter script.
     *
     * @throws Ingo_Exception
     */
    public function setScriptActive($script)
    {
        $this->_connect();

        try {
            if (!empty($script['script'])) {
                $this->_vfs->put($this->_params['root'] .$script['name'], $script['script']);
            } elseif ($this->_vfs->has($this->_params['root'] . $script['name'])) {
                $this->_vfs->delete($this->_params['root'] . $script['name']);
            }
        } catch (Flysystem\Exception $e) {
            throw new Ingo_Exception($e);
        }
    }

    /**
     * Returns the content of the currently active script.
     *
     * @return boolean|array  The complete ruleset of the specified user.
     * @throws Ingo_Exception
     * @throws Flysystem\FileNotFoundException
     */
    public function getScript()
    {
        $this->_connect();

        if (!$this->_vfs->has($this->_params['root'] . $this->_params['filename'])) {
            return false;
        }

        return array(
            'name' => $this->_params['filename'],
            'script' => $this->_vfs->read($this->_params['root'] . $this->_params['filename'])
        );
    }

    /**
     * Connect to the VFS server.
     *
     * @throws Ingo_Exception
     */
    protected function _connect()
    {
        $rc = rcmail::get_instance();

        /* Do variable substitution. */
        if (!empty($this->_params['root'])) {
            $this->_params['root'] = str_replace(
                array('%u', '%d', '%U', '%u_full'),
                array($rc->get_user_name(), rcube_utils::parse_host($rc->config->get('hostname')), $this->_params['username'], $rc->get_user_email()),
                $this->_params['root']);
        }

        if (!empty($this->_vfs)) {
            return;
        }

        switch ($this->_params['vfstype']) {
            case 'ftp':
                $adapter = Adapter\Ftp::class;
                break;
            default:
                throw new Ingo_Exception('Cannot find specified driver');
        }

        try {
            $this->_vfs = $filesystem = new Filesystem(new $adapter($this->_params));
        } catch (Flysystem\Exception $e) {
            throw new Ingo_Exception($e);
        }
    }
}
