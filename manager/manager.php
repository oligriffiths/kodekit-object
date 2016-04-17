<?php
/**
 * Kodekit - http://timble.net/kodekit
 *
 * @copyright   Copyright (C) 2007 - 2016 Johan Janssens and Timble CVBA. (http://www.timble.net)
 * @license     MPL v2.0 <https://www.mozilla.org/en-US/MPL/2.0>
 * @link        https://github.com/timble/kodekit for the canonical source repository
 */

namespace Kodekit\Library;

/**
 * Object manager
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Kodekit\Library\Object\Manager
 */
final class ObjectManager implements ObjectInterface, ObjectManagerInterface, ObjectSingleton
{
    /**
     * The object identifier
     *
     * @var ObjectIdentifier
     */
    private $__object_identifier;

    /**
     * The object registry
     *
     * @var ObjectRegistry
     */
    private $__registry;

    /*
    * The class loader
    *
    * @var ClassLoader
    */
    private $__loader;

    /**
     * Debug
     *
     * @var boolean
     */
    protected $_debug = false;

    /**
     * The identifier locators
     *
     * @var array
     */
    protected $_locators = array();

    /**
     * Constructor
     *
     * Prevent creating instances of this class by making the constructor private
     */
    public function __construct()
    {
        $this->__registry = new ObjectRegistry();

        //Create the object identifier
        $this->__object_identifier = $this->getIdentifier('object.manager');

        //Manually register the library loader
        $config = new ObjectConfig(array(
            'object_manager'    => $this,
            'object_identifier' => new ObjectIdentifier('lib:object.locator.library')
        ));

        $this->registerLocator(new ObjectLocatorLibrary($config));

        //Register self and set a 'manager' alias
        $this->setObject('lib:object.manager', $this);
        $this->registerAlias('lib:object.manager', 'manager');
    }

    /**
     * Get an object instance based on an object identifier
     *
     * If the object implements the ObjectInstantiable interface the manager will delegate object instantiation
     * to the object itself.
     *
     * @param   mixed $identifier An ObjectIdentifier, identifier string or object implementing ObjectInterface
     * @param   array $config     An optional associative array of configuration settings
     * @return  ObjectInterface  Return object on success, throws exception on failure
     * @throws  ObjectExceptionInvalidIdentifier If the identifier is not valid
     * @throws  ObjectExceptionInvalidObject     If the object doesn't implement the ObjectInterface
     * @throws  ObjectExceptionNotFound          If object cannot be loaded
     * @throws  ObjectExceptionNotInstantiated   If object cannot be instantiated
     */
    public function getObject($identifier, array $config = array())
    {
        $identifier = $this->getIdentifier($identifier);

        if (!$instance = $this->isRegistered($identifier))
        {
            //Instantiate the identifier
            $instance = $this->_instantiate($identifier, $config);

            //Mixins's are early mixed in Object::_construct()
            //$instance = $this->_mixin($identifier, $instance);

            //Decorate the object
            $instance = $this->_decorate($identifier, $instance);

            //Auto register the object
            if($this->isMultiton($identifier)) {
                $this->setObject($identifier, $instance);
            }
        }

        return $instance;
    }

    /**
     * Insert the object instance using the identifier
     *
     * @param mixed $identifier An ObjectIdentifier, identifier string or object implementing ObjectInterface
     * @param object $object    The object instance to store
     * @throws ObjectExceptionInvalidIdentifier If the identifier is not valid
     * @return void
     */
    public function setObject($identifier, $object)
    {
        $identifier = $this->getIdentifier($identifier);

        //Add alias for singletons
        if($identifier->getType() != 'lib' && $this->isSingleton($identifier))
        {
            $parts = $identifier->toArray();

            unset($parts['type']);
            unset($parts['domain']);
            unset($parts['package']);

            //Create singleton identifier : path.name
            $singleton = $this->getIdentifier($parts);

            $this->registerAlias($identifier, $singleton);
        }

        $this->__registry->set($identifier, $object);
    }

    /**
     * Returns an identifier object.
     *
     * Accepts various types of parameters and returns a valid identifier. Parameters can either be an
     * object that implements ObjectInterface, or a ObjectIdentifier object, or valid identifier
     * string. Function recursively resolves identifier aliases and returns the aliased identifier.
     *
     * If the identifier does not have a type set and it's not an alias the type will default to 'lib'.
     * Eg, event.publisher is the same as lib:event.publisher.
     *
     * If no identifier is passed the object identifier of this object will be returned.
     *
     * @param mixed $identifier An ObjectIdentifier, identifier string or object implementing ObjectInterface
     * @return ObjectIdentifier
     * @throws ObjectExceptionInvalidIdentifier If the identifier is not valid
     */
    public function getIdentifier($identifier = null)
    {
        //Get the identifier
        if(isset($identifier))
        {
            if(!$identifier instanceof ObjectIdentifierInterface)
            {
                if ($identifier instanceof ObjectInterface) {
                    $identifier = $identifier->getIdentifier();
                } else {
                    $identifier = new ObjectIdentifier($identifier);
                }
            }

            //Get the identifier object
            if (!$result = $this->__registry->find($identifier)) {
                $result = $this->__registry->set($identifier);
            }
        }
        else $result = $this->__object_identifier;

        return $result;
    }

    /**
     * Set an identifier
     *
     * This function will reset the identifier if it has already been set. Use this very carefully as it can have
     * unwanted side-effects.
     *
     * @param ObjectIdentifier  $identifier An ObjectIdentifier
     * @return ObjectManager
     */
    public function setIdentifier(ObjectIdentifier $identifier)
    {
        $this->__registry->set($identifier);
        return $this;
    }

    /**
     * Check if an identifier exists
     *
     * @param mixed $identifier An ObjectIdentifier, identifier string or object implementing ObjectInterface
     * @return bool TRUE if the identifier exists, false otherwise.
     */
    public function hasIdentifier($identifier)
    {
        return $this->__registry->has($identifier);
    }

    /**
     * Get the identifier class
     *
     * @param mixed $identifier An ObjectIdentifier, identifier string or object implementing ObjectInterface
     * @param bool  $fallback   Use fallbacks when locating the class. Default is TRUE.
     * @return string|false  Returns the class name or false if the class could not be found.
     */
    public function getClass($identifier, $fallback = true)
    {
        $identifier = $this->getIdentifier($identifier);
        $class      = $this->_locate($identifier, $fallback);

        return $class;
    }

    /**
     * Get the object configuration
     *
     * @param mixed  $identifier An ObjectIdentifier, identifier string or object implementing ObjectInterface
     * @return ObjectConfig
     * @throws ObjectExceptionInvalidIdentifier  If the identifier is not valid
     */
    public function getConfig($identifier = null)
    {
        $config = $this->getIdentifier($identifier)->getConfig();
        return $config;
    }

    /**
     * Register a mixin for an identifier
     *
     * The mixin is mixed when the identified object is first instantiated see {@link get} The mixin is also mixed with
     * with the represented by the identifier if the object is registered in the object manager. This mostly applies to
     * singletons but can also apply to other objects that are manually registered.
     *
     * @param mixed $identifier An ObjectIdentifier, identifier string or object implementing ObjectInterface
     * @param mixed $mixin      An ObjectIdentifier, identifier string or object implementing ObjectMixinInterface
     * @param array $config     Configuration for the mixin
     * @return ObjectManager
     * @throws ObjectExceptionInvalidIdentifier If the identifier is not valid
     * @see ObjectMixable::mixin()
     */
    public function registerMixin($identifier, $mixin, $config = array())
    {
        $identifier = $this->getIdentifier($identifier);

        if ($mixin instanceof ObjectMixinInterface || $mixin instanceof ObjectIdentifier) {
            $identifier->getMixins()->append(array($mixin));
        } else {
            $identifier->getMixins()->append(array($mixin => $config));
        }

        //If the identifier already exists mixin the mixin
        if ($this->isRegistered($identifier))
        {
            $mixer = $this->__registry->get($identifier);
            $this->_mixin($identifier, $mixer);
        }

        return $this;
    }

    /**
     * Register a decorator  for an identifier
     *
     * The object is decorated when it's first instantiated see {@link get} The object represented by the identifier is
     * also decorated if the object is registered in the object manager. This mostly applies to singletons but can also
     * apply to other objects that are manually registered.
     *
     * @param mixed $identifier An ObjectIdentifier, identifier string or object implementing ObjectInterface
     * @param mixed $decorator  An ObjectIdentifier, identifier string or object implementing ObjectDecoratorInterface
     * @param array $config     Configuration for the decorator
     * @return ObjectManager
     * @throws ObjectExceptionInvalidIdentifier If the identifier is not valid
     * @see ObjectDecoratable::decorate()
     */
    public function registerDecorator($identifier, $decorator, $config = array())
    {
        $identifier = $this->getIdentifier($identifier);

        if ($decorator instanceof ObjectDecoratorInterface || $decorator instanceof ObjectIdentifier) {
            $identifier->getDecorators()->append(array($decorator));
        } else {
            $identifier->getDecorators()->append(array($decorator => $config));
        }

        //If the identifier already exists decorate it
        if ($this->isRegistered($identifier))
        {
            $delegate = $this->__registry->get($identifier);
            $this->_decorate($identifier, $delegate);
        }

        return $this;
    }

    /**
     * Register an object locator
     *
     * @param mixed $identifier An ObjectIdentifier, identifier string or object implementing ObjectLocatorInterface
     * @param array $config
     * @throws \UnexpectedValueException
     * @return ObjectManager
     */
    public function registerLocator($identifier, array $config = array())
    {
        if(!$identifier instanceof ObjectLocatorInterface)
        {
            $locator = $this->getObject($identifier, $config);

            if(!$locator instanceof ObjectLocatorInterface)
            {
                throw new \UnexpectedValueException(
                    'Locator: '.get_class($locator).' does not implement ObjectLocatorInterface'
                );
            }
        }
        else $locator = $identifier;

        //Add the locator by name and type
        $this->_locators[$locator->getType()] = $locator;
        $this->_locators[$locator->getIdentifier()->getName()] = $locator;

        return $this;
    }

    /**
     * Get a registered object locator based on his type
     *
     * @param string $type The locator type
     * @return ObjectLocatorInterface|null  Returns the object locator or NULL if it cannot be found.
     */
    public function getLocator($type)
    {
        $result = null;

        if(isset($this->_locators[$type])) {
            $result = $this->_locators[$type];
        }

        return $result;
    }

    /**
     * Get the registered class locators
     *
     * @return array
     */
    public function getLocators()
    {
        return $this->_locators;
    }

    /**
     * Set an alias for an identifier
     *
     * @param mixed $identifier An ObjectIdentifier, identifier string or object implementing ObjectInterface
     * @param string $alias     The identifier alias
     * @return ObjectManager
     * @throws ObjectExceptionInvalidIdentifier If the identifier is not valid
     */
    public function registerAlias($identifier, $alias)
    {
        $identifier = $this->getIdentifier($identifier);
        $alias      = $this->getIdentifier($alias);

        //Register the alias for the identifier
        $this->__registry->alias($identifier, (string) $alias);

        //Merge alias configuration into the identifier
        $identifier->getConfig()->append($alias->getConfig());

        // Register alias mixins.
        foreach ($alias->getMixins() as $mixin) {
            $this->registerMixin($identifier, $mixin);
        }

        return $this;
    }

    /**
     * Get the aliases for an identifier
     *
     * @param mixed $identifier An ObjectIdentifier, identifier string or object implementing ObjectInterface
     * @return array   An array of aliases
     * @throws ObjectExceptionInvalidIdentifier If the identifier is not valid
     */
    public function getAliases($identifier)
    {
        return array_keys($this->__registry->getAliases(), (string) $identifier);
    }

    /**
     * Get the class loader
     *
     * @return ClassLoaderInterface
     */
    public function getClassLoader()
    {
        return $this->__loader;
    }

    /**
     * Set the class loader
     *
     * @param  ClassLoaderInterface $loader
     * @return ObjectManagerInterface
     */
    public function setClassLoader(ClassLoaderInterface $loader)
    {
        $this->__loader = $loader;
        return $this;
    }

    /**
     * Check if the object instance exists based on the identifier
     *
     * @param  mixed $identifier An ObjectIdentifier, identifier string or object implementing ObjectInterface
     * @return ObjectInterface|false Returns the registered object on success or FALSE on failure.
     * @throws ObjectExceptionInvalidIdentifier If the identifier is not valid
     */
    public function isRegistered($identifier)
    {
        $result = false;

        try
        {
            $registered = $this->getIdentifier($identifier);

            //Get alias for singletons
            if($registered->getType() != 'lib' && $this->isSingleton($registered))
            {
                $parts = $registered->toArray();

                unset($parts['type']);
                unset($parts['domain']);
                unset($parts['package']);

                //Create singleton identifier : path.name
                $registered = $this->getIdentifier($parts);
            }

            $object = $this->__registry->get($registered);

            //If the object implements ObjectInterface we have registered an object
            if($object instanceof ObjectInterface) {
                $result = $object;
            }

        } catch (ObjectExceptionInvalidIdentifier $e) {}

        return $result;
    }

    /**
     * Check if the object is a multiton
     *
     * @param mixed $identifier An object that implements the ObjectInterface, an ObjectIdentifier or valid identifier string
     * @return boolean Returns TRUE if the object is a singleton, FALSE otherwise.
     */
    public function isMultiton($identifier)
    {
        $result = false;
        $class  = $this->getClass($identifier);

        if($class) {
            $result = array_key_exists(__NAMESPACE__.'\ObjectMultiton', class_implements($class));
        }

        return $result;

    }

    /**
     * Check if the object is a singleton
     *
     * @param mixed $identifier An object that implements the ObjectInterface, an ObjectIdentifier or valid identifier string
     * @return boolean Returns TRUE if the object is a singleton, FALSE otherwise.
     */
    public function isSingleton($identifier)
    {
        $result = false;
        $class  = $this->getClass($identifier);

        if($class) {
            $result = array_key_exists(__NAMESPACE__.'\ObjectSingleton', class_implements($class));
        }

        return $result;
    }

    /**
     * Check if the identifier is an alias
     *
     * @param  mixed $identifier An ObjectIdentifier, identifier string or object implementing ObjectInterface
     * @return boolean Returns TRUE if the identifiers is an alias FALSE otherwise
     */
    public function isAlias($identifier)
    {
        return array_key_exists ($this->__registry->getAliases(), (string) $identifier);
    }

    /**
     * Enable or disable the cache
     *
     * @param bool $cache True or false.
     * @param string $namespace The cache namespace
     * @return ObjectManager
     */
    public function setCache($cache, $namespace = null)
    {
        if($cache && ObjectRegistryCache::isSupported())
        {
            $this->__registry = new ObjectRegistryCache();

            if($namespace) {
                $this->__registry->setNamespace($namespace);
            }
        }
        else
        {
            if(!$this->__registry instanceof ObjectRegistry) {
                $this->__registry = new ObjectRegistry();
            }
        }

        return $this;
    }

    /**
     * Check if caching is enabled
     *
     * @return bool
     */
    public function isCache()
    {
        return $this->__registry instanceof ClassRegistryCache;
    }

    /**
     * Enable or disable debug
     *
     * @param bool|null $debug True or false.
     * @return ObjectManager
     */
    public function setDebug($debug)
    {
        $this->_debug = (bool) $debug;
        return $this;
    }

    /**
     * Check if the object manager is running in debug mode
     *
     * @return bool
     */
    public function isDebug()
    {
        return $this->_debug;
    }

    /**
     * Perform the actual mixin of all registered mixins for an object
     *
     * @param  ObjectIdentifier $identifier
     * @param  ObjectMixable    $mixer
     * @return ObjectMixable    The mixed object
     */
    protected function _mixin(ObjectIdentifier $identifier, $mixer)
    {
        if ($mixer instanceof ObjectMixable)
        {
            $mixins = $identifier->getMixins();

            foreach ($mixins as $key => $value)
            {
                if (is_numeric($key)) {
                    $mixer->mixin($value);
                } else {
                    $mixer->mixin($key, $value);
                }
            }
        }

        return $mixer;
    }

    /**
     * Perform the actual decoration of all registered decorators for an object
     *
     * @param  ObjectIdentifier  $identifier
     * @param  ObjectDecoratable $delegate
     * @return ObjectDecorator  The decorated object
     */
    protected function _decorate(ObjectIdentifier $identifier, $delegate)
    {
        if ($delegate instanceof ObjectDecoratable)
        {
            $decorators = $identifier->getDecorators();

            foreach ($decorators as $key => $value)
            {
                if (is_numeric($key)) {
                    $delegate = $delegate->decorate($value);
                } else {
                    $delegate = $delegate->decorate($key, $value);
                }
            }
        }

        return $delegate;
    }

    /**
     * Configure an identifier
     *
     * @param ObjectIdentifier $identifier
     * @param array             $data
     * @return ObjectConfig
     */
    protected function _configure(ObjectIdentifier $identifier, array $data = array())
    {
        //Prevent config settings from being stored in the identifier
        $config = clone $identifier->getConfig();

        //Append the config data from the singleton
        if($identifier->getType() != 'lib' && $this->isSingleton($identifier))
        {
            $parts = $identifier->toArray();

            unset($parts['type']);
            unset($parts['domain']);
            unset($parts['package']);

            //Append the config from the singleton
            $config->append($this->getIdentifier($parts)->getConfig());
        }

        //Append the config data for the object
        $config->append($data);

        //Set the service container and identifier
        $config->object_manager    = $this;
        $config->object_identifier = $identifier;

        return $config;
    }

    /**
     * Get an instance of a class based on a class identifier
     *
     * @param ObjectIdentifier $identifier
     * @param bool              $fallback   Use fallbacks when locating the class. Default is TRUE.
     * @return  string  Return the identifier class or FALSE on failure.
     */
    protected function _locate(ObjectIdentifier $identifier, $fallback = true)
    {
        $class = $this->__registry->getClass($identifier);

        //If the class is FALSE we have tried to locate it already, do not locate it again.
        if(empty($class) && ($class !== false))
        {
            $class = $this->_locators[$identifier->getType()]->locate($identifier, $fallback);

            //If we are falling back set the class in the registry
            if($fallback)
            {
                if(!$this->__registry->get($identifier) instanceof ObjectInterface) {
                    $this->__registry->setClass($identifier, $class);
                }
            }
        }

        return $class;
    }

    /**
     * Get an instance of a class based on a class identifier
     *
     * @param   ObjectIdentifier $identifier
     * @param   array              $config      An optional associative array of configuration settings.
     * @return  object  Return object on success, throws exception on failure
     * @throws	ObjectExceptionInvalidObject     If the object doesn't implement the ObjectInterface
     * @throws  ObjectExceptionNotFound          If object cannot be loaded
     * @throws  ObjectExceptionNotInstantiated   If object cannot be instantiated
     */
    protected function _instantiate(ObjectIdentifier $identifier, array $config = array())
    {
        $result = null;

        //Get the class name and set it in the identifier
        $class = $this->getClass($identifier);

        if($class && class_exists($class))
        {
            if (!array_key_exists(__NAMESPACE__.'\ObjectInterface', class_implements($class, false)))
            {
                throw new ObjectExceptionInvalidObject(
                    'Object: '.$class.' does not implement ObjectInterface'
                );
            }

            //Configure the identifier
            $config = $this->_configure($identifier, $config);

            // Delegate object instantiation.
            if (array_key_exists(__NAMESPACE__.'\ObjectInstantiable', class_implements($class, false))) {
                $result = call_user_func(array($class, 'getInstance'), $config, $this);
            } else {
                $result = new $class($config);
            }

            //Thrown an error if no object was instantiated
            if (!is_object($result))
            {
                throw new ObjectExceptionNotInstantiated(
                    'Cannot instantiate object from identifier: ' . $class
                );
            }
        }
        else throw new ObjectExceptionNotFound('Cannot load object from identifier: '. $identifier);

        return $result;
    }
}
