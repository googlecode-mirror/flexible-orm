<?php
/**
 * @file
 * @author jarrod.swift
 */
namespace ORM\Controller;
use ORM\Interfaces\ClassRegister;

/**
 * Register of controllers
 * 
 * Controllers can be explicitly registered as an alias to a class name (using
 * registerController()) or automatically registered using a namespace (see registerNamespace()).
 * 
 * When using namespaces, it assumes the controller class is immediately under the
 * namespace (see naming rules below) and is a subclass of BaseController
 * 
 * <b>Namespace Rules</b>
 * An alias is converted to a class name and then searched for in all registered
 * namespaces.
 *  - First letter capitalised
 *  - Converted from underscores to camel case
 * 
 * e.g. \c simulations becomes \c Simulations and \c users_jobs becomes \c UsersJobs
 *
 * @see ControllerFactory, BaseController
 */
class ControllerRegister implements ClassRegister {
    /**
     * Base class for all controllers
     */
    const CONTROLLER_CLASS = 'ORM\Controller\BaseController';
    
    /**
     * @var array $namespaces
     */
    protected $namespaces = array();

    /**
     * @var array $registeredControllers
     */
    protected $registeredControllers = array();

    /**
     * Add a namespace container for controllers
     * 
     * @param string $namespace
     * @return array 
     */
    public function registerNamespace( $namespace ) {
        $this->namespaces[] = $namespace;
        return $this->namespaces;
    }
    
    /**
     * Register a specific class as a controller
     * 
     * @param string $name
     * @param string $class
     * @return array 
     */
    public function registerController( $name, $class ) {
        $this->registeredControllers[$name] = $class;
        return $this->registeredControllers;
    }
    
    /**
     * Get the controller for a specified controller name alias
     *  
     * @param string $controllerName
     * @return string
     *      A fully qualified class name for the controller
     */
    public function getClassName( $controllerName ) {
        if( array_key_exists($controllerName, $this->registeredControllers) ) {
            return $this->registeredControllers[$controllerName];
        }
        
        $class_name = $this->_controllerToClassName($controllerName);
        
        foreach( $this->namespaces as $namespace ) {
            $qualifiedClassName = "$namespace\\$class_name";
            if(class_exists($qualifiedClassName) && is_subclass_of($qualifiedClassName, self::CONTROLLER_CLASS)) {
                return $qualifiedClassName;
            }
        }
        
        return false;
    }
    
    /**
     * Convert a controller name to a class base name
     * 
     * @param string $controllerName
     * @return string 
     */
    private function _controllerToClassName( $controllerName ) {
        $words = ucwords(str_replace('_',' ', $controllerName));
        return str_replace(' ', '', $words);
    }
}