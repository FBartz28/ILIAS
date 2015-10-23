<?php

require_once 'Services/Authentication/classes/PDO/interface.ilAuthInterface.php';

/**
 * @property  _postPassword
 */
class ilPDOAuthentication implements ilAuthInterface {


    protected $_sessionName = '_authsession';
    protected $allowLogin = true;
    protected $_postUsername = 'username';
    protected $_postPassword = 'password';
    protected $advancedsecurity;
    protected $enableLogging;
    protected $regenerateSessionId;

    protected $status = '';
    protected $username = null;
    protected $password;

    protected $session;
    protected $server;
    protected $post;
    protected $cookie;


    public function __construct() {
//        $started = session_start();
//        $sess = session_id();
//        $db_session_handler = new ilSessionDBHandler();
//        if (!$db_session_handler->setSaveHandler())
//        {
//            throw new Exception("Disable save mode or set session_hanlder to \"user\"");
//        }
        session_start();

        $this->session =& $_SESSION[$this->_sessionName];
        $this->server =& $_SERVER;
        $this->post =& $_POST;
        $this->cookie =& $_COOKIE;

    }

    public function setIdle($time, $add = false)
    {
        // TODO: Implement setIdle() method.
    }

    /**
     * Set the maximum expire time
     * @param int $time Time in seconds
     * @param bool $add Add time to current expire time or not
     * @return void
     */
    public function setExpire($time, $add = false)
    {
        // TODO: Implement setExpire() method.
    }


    /**
     * Start new auth session
     * @return void
     */
    public function start()
    {
        // TODO SAME AS old AUTH
        $this->assignData();
        if(!$this->checkAuth() && $this->allowLogin)
            $this->login();
    }

    protected function checkAuth() {
        return isset($this->session['username']);
    }

    protected function login() {
        if (!empty($this->username) && $this->verifyPassword($this->username, $this->password)) {
            $this->setAuth($this->username);
        } else {
            $this->status = AUTH_WRONG_LOGIN;
        }
    }

    /**
     * Has the user been authenticated?
     *
     * Is there a valid login session. Previously this was different from
     * checkAuth() but now it is just an alias.
     *
     * @return bool  True if the user is logged in, otherwise false.
     */
    function getAuth()
    {
        return $this->checkAuth();
    }

    /**
     * @return string
     */
    function getStatus()
    {
        // TODO: Implement getStatus() method.
        return $this->status;
    }


    /**
     * @return string
     */
    function getUsername()
    {
        return $this->session['username'];
    }

    /**
     * Returns the time up to the session is valid
     *
     * @access public
     * @return integer
     */
    function sessionValidThru()
    {
        return time() + 1000000;
    }

    public function logout(){
        $this->session = null;
    }

    protected function assignData()
    {
        if (isset($this->post[$this->_postUsername])
            && $this->post[$this->_postUsername] != ''
        ) {
            $this->username = (get_magic_quotes_gpc() == 1
                ? stripslashes($this->post[$this->_postUsername])
                : $this->post[$this->_postUsername]);
        }
        if (isset($this->post[$this->_postPassword])
            && $this->post[$this->_postPassword] != ''
        ) {
            $this->password = (get_magic_quotes_gpc() == 1
                ? stripslashes($this->post[$this->_postPassword])
                : $this->post[$this->_postPassword]);
        }
    }

    private function setAuth($username)
    {
        session_regenerate_id(true);

        if(!isset($this->session))
            $this->session = array();
        $this->session['username'] = $username;
        $_SESSION['fuckamee'] = true;
    }

    private function verifyPassword($username, $password)
    {
//        return true;
        global $ilDB;
        $passhash = md5($password);
        $query = "SELECT * FROM usr_data WHERE login LIKE ".$ilDB->quote($username, 'text')." and passwd LIKE ".$ilDB->quote($passhash, 'text');
        $res = $ilDB->query($query);
        if($row = $ilDB->fetchAssoc($res)) {
            return true;
        } else {
            return false;
        }
    }
}