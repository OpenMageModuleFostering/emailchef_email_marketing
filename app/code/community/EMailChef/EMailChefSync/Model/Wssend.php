<?php

require_once __DIR__.'/../lib/emailchef/vendor/autoload.php';
class EMailChefWsSend
{
    /**
     * @var int
     */
    protected $storeId;
    /**
     * @var EMailChef_EMailChefSync_Model_Config
     */
    protected $_config;

    public function __construct($storeId = null)
    {
        $this->setStoreId($storeId);
    }

    /**
     * Set the store ID.
     *
     * @param int
     */
    public function setStoreId($id)
    {
        $this->storeId = $id;

        return $this;
    }

    public function __destruct()
    {
    }

    /**
     * Login, returning access key or false on failing to login.
     *
     * @param null $user
     * @param null $pwd
     *
     * @return false|string Access key or false
     */
    public function loginFromId($user = null, $pwd = null)
    {
        // login with webservice user
        $user = ($user !== null) ? $user : Mage::getStoreConfig('emailchef_newsletter/emailchef/username_ws', $this->storeId);
        $pwd = ($pwd !== null) ? $pwd : Mage::getStoreConfig('emailchef_newsletter/emailchef/password_ws', $this->storeId);

        return $this->_loginFromId($user, $pwd);
    }

    /**
     * Login, returning access key or false on failing to login.
     *
     * @param null $user
     * @param null $pwd
     *
     * @return string|false Access key or false
     */
    protected function _loginFromId($user, $pwd)
    {
        try {
            $getAuthenticationTokenCommand = new \EMailChef\Command\Api\GetAuthenticationTokenCommand();
            $accessKey = $getAuthenticationTokenCommand->execute($user, $pwd);

            return $accessKey;
        } catch (Exception $e) {
            Mage::log($e->getMessage(), 0);
            $errorDescription = $e->getMessage();
        }

        $GLOBALS['__sl_emailchef_login_error'] = $errorDescription;

        return false;
    }

    public function GetFields($accessKey, $list = null)
    {
        $list = ($list !== null) ? $list : Mage::getStoreConfig('emailchef_newsletter/emailchef/list', $this->storeId);
        $fields = array();
        try {
            $getPredefinedFieldsCommand = new \EMailChef\Command\Api\GetPredefinedFieldsCommand();
            $predefinedFields = $getPredefinedFieldsCommand->execute($accessKey);

            $getListFieldsCommand = new \EMailChef\Command\Api\GetListFieldsCommand();
            $fields = $getListFieldsCommand->execute($list, $accessKey);

            $fields = array_merge($predefinedFields, $fields);
        } catch (Exception $e) {
            Mage::log('Custom exception', 0);
            Mage::log($e->getMessage(), 0);
        }

        return $fields;
    }

    public function logout()
    {
        throw new \Exception('not yet implemented');
    }

    //TODO: seems unused, remove if so
    public function getAccessKey()
    {
        return $this->accessKey;
    }

    public function option($key, $value)
    {
        return array('Key' => $key, 'Value' => $value);
    }

    /**
     * Get the config.
     *
     * @reutrn EMailChef_EMailChefSync_Model_Config
     */
    protected function _config()
    {
        if (null === $this->_config) {
            $this->_config = Mage::getModel('emailchef/config');
        }

        return $this->_config;
    }
}
