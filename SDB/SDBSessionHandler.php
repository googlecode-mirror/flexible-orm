<?php
/**
 * @file
 * @author jarrod.swift
 */
namespace ORM\SDB;

/**
 * Allow sessions to be stored in SDB instead of locally
 *
 * The advantage of this is that sessions are then independant of a machine,
 * so they can span a cluster of machines. Similar to using memcached, but does
 * not need to be configured. Will most likely be slightly slower than memcached.
 *
 * To use, simply call:
 * @code
 * SDBSessionHandler::Register()
 * @endcode
 *
 * @todo Complete and Test
 */
class SDBSessionHandler {
    /**
     * @var AmazonSDB $_sdb
     */
    private $_sdb;

    /**
     * @var SDBSessionHandler $_sessionHandler
     */
    private static $_sessionHandler;

    /**
     * Singleton enforcing constructor
     *
     * Doesn't actually do anything except ensure that this class cannot be
     * instantiated externally.
     */
    private function __construct() {}

    /**
     * Register this class as the Session handler
     *
     * @return boolean
     *      True on success
     */
    public static function Register() {
        $sessionHandler = self::Get();

        return session_set_save_handler(
            array($sessionHandler, "open"),
            array($sessionHandler, "close"),
            array($sessionHandler, "read"),
            array($sessionHandler, "write"),
            array($sessionHandler, "destroy"),
            array($sessionHandler, "gc")
        );
    }

    /**
     * Get the single instance of SDBSessionHandler
     * 
     * @return SDBSessionHandler
     */
    public static function Get() {
        if( is_null(self::$_sessionHandler) ) {
            self::$_sessionHandler = new SDBSessionHandler();
        }

        return self::$_sessionHandler;
    }

    public function open( $savePath, $sessionName ) {
        $this->_sdb = new \AmazonSDB();
        $this->_sdb->set_response_class( __NAMESPACE__.'\SDBResponse');
        $this->_sdb->set_region(\AmazonSDB::REGION_APAC_SE1);
        $this->_sdb->set_cache_config('apc');

        $this->_sdb->create_domain( SDBSession::TableName() );

        return true;
    }

    public function close() {
        unset($this->_sdb);
        return true;
    }

    public function read( $id ) {
        $session = SDBSession::Find( $id );

        return $session ? $session->data : '';
    }

    public function write( $id, $data ) {
        $session        = new SDBSession();
        $session->data  = $data;
        $session->id( $id );
        
        $session->save( true );
    }

    public function destroy( $id ) {
        SDBSession::Destroy( $id );
    }

    public function gc( $max_expire_time ) {
        $oldSessions = SDBSession::FindAllByTime( $max_expire_time, '<');
        $oldSessions->delete();
    }

    public function __destruct() {
        session_write_close();
    }
}
?>
