<?php
/**
 * @file
 * @author Pierre Dumuid <pierre.dumuid@sustainabilityhouse.com.au>
 */
namespace ORM\Controller;
use \LogicException;
use ORM\Interfaces\SessionWrapper;

/**
 * A wrapper for the session variables
 *
 * Provides way to avoid locked session when multiple PHP pages are requested.
 *
 * When a web server receives multiple PHP page request from a single user simultaneous,
 * Apache will block each process until the others are finished.
 *
 * In some cases, the blocking is undesirable, particularly if the session data is used in a
 * read-only manner (i.e. to display results without any update).
 *
 * <b>Usage</b>
 *
 * Where an update is performed:
 *
 * @code
 * $session = Session::GetSession();
 * $session->lock();
 * $session->set("i", 1);
 * $session->set("j", 2);
 * $session->set("k", 3);
 * $session->unlock();
 * @endcode
 *
 * Where data only needs to be retrieved:
 *
 * @code
 * $session = Session::GetSession();
 * $i = $session->get("i");
 * echo "The value of i is $i";
 * @endcode
 *
 * Loading session, and subsequently locking it.
 *
 * @code
 * // Construction results in loading with a default of non-blocking behaviour
 * $session = Session::GetSession();
 *
 * // How (and how not) to set variables.
 * $j = $session->j;
 * 
 * $session->lock();
 * 
 * $i = $session->i;
 * $session->i = $i + 1;   // Good
 * $session->j = $j + 1;   // BAD - because "j" may have been modified by another script!
 * 
 * $session->unlock();
 * @endcode
 *
 * @todo This should probably lazy-intialise the session variable, otherwise the
 *       session is started and stopped at least twice if lock() is used.
 *
 */
class Session {
    /**
     * This class is a singleton, and the following variable contains the respective data.
     *
     * @var Session $_staticSession
     */
    protected static $_staticSession;

    /**
     * Variable to hold the variables retrieved from the session between session openings.
     *
     * @var array $_sessionVariableCache
     */
    private $_sessionVariableCache;

    /**
     * A boolean to determine if the session is locked (i.e. still open).
     *
     * @var bool $_locked
     */
    private $_locked = false;

    /**
     * Constant the defines the session name
     */
    const SESSION_NAME = 'ORM_DEFAULT';

    /**
     * Constant the defines the field of the $_SESSION variable to store the ORM values in.
     */
    const FIELD_NAME = 'ORM';

    /**
     * Index for the lock stack
     */
    private $_lockStackIndex = 0;
    
    /**
     * @var SessionWrapper $_session
     */
    protected $_session;

    /**
     * Session is a singleton class
     *
     * @param SessionWrapper $session
     *      The session wrapper to use with this object. Useful for mocking
     * 
     * @see GetSession()
     */
    private function __construct( SessionWrapper $session ) {
        $this->_session = $session;
        $this->_loadSessionVariable();
    }

    /**
     * Get the static Session instance and instantiate if necessary
     *
     * @param boolean $session
     *      [optional] You can supply different session data here. Helpful for mocking in
     *      unit testing. Will not generate a new Session object if called twice with 
     *      different SessionWrappers.
     * @return Session
     */
    public static function GetSession(SessionWrapper $session = null) {
        if ( is_null(static::$_staticSession) ) {
            if( is_null($session) ) {
                $session = new Session\SessionWrapper();
            }
            $calledClass = get_called_class();
            static::$_staticSession = new $calledClass($session);
        }

        return static::$_staticSession;
    }


    /**
     * Save all the unsaved variables if the PHP script finished
     * without calling saveSessionVariables().
     */
    public function __destruct() {
        if ($this->isLocked()) {
            $this->saveSessionVariable();
        }
    }


    /**
     * Destroy the session.
     */
    public function destroySession() {
        if (!$this->isLocked()) {
            $this->_session->start(static::SESSION_NAME);
        }
        
        $this->_sessionVariableCache = array();
        $this->_session->destroy();
    }


    /**
     * Retrieve variables from global session variable and store it in local cache
     */
    private function _loadSessionVariable() {
        $this->_session->start(static::SESSION_NAME);
        $this->_locked = true;
        
        $this->_sessionVariableCache = array();
        if(isset($this->_session[static::FIELD_NAME])) {
            $this->_sessionVariableCache = $this->_session[static::FIELD_NAME];
        }
        
        if (!$this->isLocked()) {
            $this->_session->writeClose();
            $this->_locked = false;
        }
    }

    /**
     * Save variables to session
     * 
     * \note Calling this will close the session and unlock the Session object
     * 
     * @throws LogicException if called when not in locked mode
     */
    private function _saveSessionVariable() {
        if (!$this->isLocked()) {
            throw new LogicException("Session is not in a locked condition, unable to update session variable.");
        }
        
        $this->_session[static::FIELD_NAME] = $this->_sessionVariableCache;
        $this->_session->writeClose();
        
        $this->_locked      = false;
    }

    /**
     * Retrieve a variable from the local cached variable array.
     *
     * Alternatively, you can use magic properties to access the session variable
     * 
     * <b>Usage</b>
     * @code
     * $session = Session::GetSession();
     * 
     * // Either
     * $session->get('user_name');
     * 
     * // OR
     * $session->user_name;
     * 
     * @endcode
     * 
     * @return mixed|null
     */
    public function &get($var) {
        if (array_key_exists($var, $this->_sessionVariableCache)) {
            return $this->_sessionVariableCache[$var];
        } else {
            $variableNotValid = null;
            return $variableNotValid;
        }
    }

    /**
     * Set a variable in the local cached variable array.
     *
     * Alternatively, you can use magic properties to set the session variables
     * 
     * <b>Usage</b>
     * @code
     * $session = Session::GetSession();
     * $session->lock();
     * 
     * // Either
     * $session->set('user_name', 123);
     * 
     * // OR
     * $session->user_name = 123;
     * 
     * $session->unlock();
     * @endcode
     * 
     * @throws LogicException if called when Session is not locked
     * 
     * @param string $var
     *      Name of the session variable
     * @param mixed $value
     *      The value to save
     * @return mixed
     */
    public function set($var, $value) {
        if (!$this->isLocked()) {
            throw new LogicException("Attempt to set session variable when Session is not in a locked condition.");
        }
        
        $this->_sessionVariableCache[$var]  = $value;
        $this->_unsavedData                 = true;
        
        return $value;
    }
    
    /**
     * Get a variable from the session
     * 
     * @see get()
     * @param string $var
     *      Variable name
     * @return mixed|null
     */
    public function __get( $var ) {
        return $this->get( $var );
    }
    
    /**
     * Set a variable from the session using magic properties
     * @see set()
     * @param string $var
     * @param mixed $value
     * @return mixed 
     */
    public function __set( $var, $value ) {
        return $this->set($var, $value);
    }

    public function lock() {
        if (++$this->_lockStackIndex === 1) {
            $this->_lockAndLoad();
        }
        
        return $this->_lockStackIndex;
    }

    public function unlock() {
        if (--$this->_lockStackIndex === 0) {
            $this->_unlock();
        }
    }

    /**
     * Returns if the session is locked.
     */
    public function isLocked() {
        return $this->_locked;
    }
    
    /**
     * Lock the session for editing to prevent race conditions
     * 
     * \note Will update the session variable to the current locked situation
     */
    private function _lockAndLoad() {
        if( !$this->isLocked() ) {
            $this->_loadSessionVariable();
        }
    }
    
    /**
     * Unlock the session to allow other requests to be executed
     * 
     * @see saveSessionVariable()
     */
    private function _unlock() {
        $this->_saveSessionVariable();
    }
}
