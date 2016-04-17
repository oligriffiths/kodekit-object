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
 * Abstract Object Locator
 *
 * @author  Johan Janssens <https://github.com/johanjanssens>
 * @package Kodekit\Library\Object\Locator
 */
abstract class ObjectLocatorAbstract extends Object implements ObjectLocatorInterface
{
    /**
     * The locator type
     *
     * @var string
     */
    protected static $_type = '';

    /**
     * Locator identifiers
     *
     * @var array
     */
    protected $_identifiers = array();

    /**
     * Returns a fully qualified class name for a given identifier.
     *
     * @param ObjectIdentifier $identifier An identifier object
     * @param bool  $fallback   Use the fallback sequence to locate the identifier
     * @return string|false  Return the class name on success, returns FALSE on failure
     */
    public function locate(ObjectIdentifier $identifier, $fallback = true)
    {
        $domain  = empty($identifier->domain) ? 'kodekit' : ucfirst($identifier->domain);
        $package = ucfirst($identifier->package);
        $path    = StringInflector::implode($identifier->path);
        $file    = ucfirst($identifier->name);
        $class   = $path.$file;

        $info = array(
            'identifier' => $identifier,
            'class'      => $class,
            'package'    => $package,
            'domain'     => $domain,
            'path'       => $path,
            'file'       => $file,
        );

        return $this->find($info, $fallback);
    }

    /**
     * Find a class
     *
     * @param array  $info      The class information
     * @param bool   $fallback  If TRUE use the fallback sequence
     * @return bool|mixed
     */
    public function find(array $info, $fallback = true)
    {
        $result = false;
        $missed = array();

        //Get the class templates
        if(!empty($info['domain'])) {
            $identifier = $this->getType().'://'.$info['domain'].'/'.$info['package'];
        } else {
            $identifier = $this->getType().':'.$info['package'];
        }

        $templates = $this->getClassTemplates(strtolower($identifier));

        //Find the class
        foreach($templates as $template)
        {
            $class = str_replace(
                array('<Domain>',      '<Package>'     ,'<Path>'      ,'<File>'      , '<Class>'),
                array($info['domain'], $info['package'], $info['path'], $info['file'], $info['class']),
                $template
            );

            //Do not try to locate a class twice
            if(!isset($missed[$class]) && class_exists($class))
            {
                $result = $class;
                break;
            }

            if(!$fallback) {
                break;
            }

            //Mark the class
            $missed[$class] = false;
        }

        return $result;
    }

    /**
     * Get the list of class templates for an identifier
     *
     * @param string $identifier The package identifier
     * @return array The class templates for the identifier
     */
    abstract public function getClassTemplates($identifier);

    /**
     * Register an identifier
     *
     * @param  string       $identifier
     * @param  string|array $namespace(s) Sequence of fallback namespaces
     * @return ObjectLocatorAbstract
     */
    public function registerIdentifier($identifier, $namespaces)
    {
        $this->_identifiers[$identifier] = (array) $namespaces;
        return $this;
    }

    /**
     * Get the namespace(s) for the identifier
     *
     * @param string $identifier The package identifier
     * @return array|false The namespace(s) or FALSE if the identifier does not exist.
     */
    public function getIdentifierNamespaces($identifier)
    {
        return isset($this->_identifiers[$identifier]) ?  $this->_identifiers[$identifier] : false;
    }

    /**
     * Get the type
     *
     * @return string
     */
    public static function getType()
    {
        return static::$_type;
    }
}
