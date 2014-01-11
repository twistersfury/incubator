<?php

/*
  +------------------------------------------------------------------------+
  | Phalcon Framework                                                      |
  +------------------------------------------------------------------------+
  | Copyright (c) 2011-2012 Phalcon Team (http://www.phalconphp.com)       |
  +------------------------------------------------------------------------+
  | This source file is subject to the New BSD License that is bundled     |
  | with this package in the file docs/LICENSE.txt.                        |
  |                                                                        |
  | If you did not receive a copy of the license and are unable to         |
  | obtain it through the world-wide-web, please send an email             |
  | to license@phalconphp.com so we can send you a copy immediately.       |
  +------------------------------------------------------------------------+
  | Authors: Andres Gutierrez <andres@phalconphp.com>                      |
  |          Eduar Carvajal <eduar@phalconphp.com>                         |
  +------------------------------------------------------------------------+
*/

namespace Phalcon\Acl\Adapter;

use Phalcon\Acl\Adapter;
use Phalcon\Acl\AdapterInterface;
use Phalcon\Acl\Exception;
use Phalcon\Acl\Resource;
use Phalcon\Acl;
use Phalcon\Acl\Role;

/**
 * Phalcon\Acl\Adapter\Mongo
 * Manages ACL lists using Mongo Collections
 */
class Mongo extends Adapter implements AdapterInterface
{

    /**
     * @var array
     */
    protected $_options;

    /**
     * Phalcon\Acl\Adapter\Mongo
     *
     * @param $options
     *
     * @throws \Phalcon\Acl\Exception
     */
    public function __construct($options)
    {

        if (!is_array($options)) {
            throw new Exception("Acl options must be an array");
        }

        if (!isset($options['db'])) {
            throw new Exception("Parameter 'db' is required");
        }

        if (!isset($options['roles'])) {
            throw new Exception("Parameter 'roles' is required");
        }

        if (!isset($options['resources'])) {
            throw new Exception("Parameter 'resources' is required");
        }

        if (!isset($options['resourcesAccesses'])) {
            throw new Exception("Parameter 'resourcesAccesses' is required");
        }

        if (!isset($options['accessList'])) {
            throw new Exception("Parameter 'accessList' is required");
        }

        $this->_options = $options;
    }

    /**
     * Returns a mongo collection
     *
     * @param string $name
     * @return \MongoCollection
     */
    protected function _getCollection($name)
    {
        return $this->_options['db']->selectCollection($this->_options[$name]);
    }

    /**
     * Adds a role to the ACL list. Second parameter lets to inherit access data from other existing role
     * Example:
     * <code>$acl->addRole(new Phalcon\Acl\Role('administrator'), 'consultor');</code>
     * <code>$acl->addRole('administrator', 'consultor');</code>
     *
     * @param  string $role
     * @param  array  $accessInherits
     * @return boolean
     */
    public function addRole($role, $accessInherits = null)
    {

        if (!is_object($role)) {
            $role = new Role($role);
        }

        $roles = $this->_getCollection('roles');

        $exists = $roles->count(array('name' => $role->getName()));
        if (!$exists) {

            $roles->insert(array(
                'name'        => $role->getName(),
                'description' => $role->getDescription()
            ));

            $this->_getCollection('accessList')->insert(array(
                'roles_name'     => $role->getName(),
                'resources_name' => '*',
                'access_name'    => '*',
                'allowed'        => $this->_defaultAccess
            ));
        }

        if ($accessInherits) {
            return $this->addInherit($role->getName(), $accessInherits);
        }

        return true;
    }

    /**
     * Do a role inherit from another existing role
     *
     * @param string $roleName
     * @param string $roleToInherit
     *
     * @throws \Phalcon\Acl\Exception
     */
    public function addInherit($roleName, $roleToInherit)
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->_options['roles'] . " WHERE name = ?";
        $exists = $this->_options['db']->fetchOne($sql, null, array($roleToInherit));
        if (!$exists[0]) {
            throw new Exception("Role '" . $roleToInherit . "' does not exist in the role list");
        }

        $sql = 'SELECT COUNT(*) FROM ' . $this->_options['rolesInherits'] . " WHERE roles_name = ? AND roles_inherit = ?";
        $exists = $this->_options['db']->fetchOne($sql, null, array($roleName, $roleToInherit));
        if (!$exists[0]) {
            $this->_options['db']->execute('INSERT INTO ' . $this->_options['rolesInherits'] . " VALUES (?, ?)", array($roleName, $roleToInherit));
        }
    }

    /**
     * Check whether role exist in the roles list
     *
     * @param  string $roleName
     * @return boolean
     */
    public function isRole($roleName)
    {
        $exists = $this->_getCollection('roles')->count(array('name' => $roleName));
        return $exists > 0;
    }

    /**
     * Check whether resource exist in the resources list
     *
     * @param  string $resourceName
     * @return boolean
     */
    public function isResource($resourceName)
    {
        $exists = $this->_getCollection('resources')->count(array('name' => $resourceName));
        return $exists > 0;
    }

    /**
     * Adds a resource to the ACL list
     * Access names can be a particular action, by example
     * search, update, delete, etc or a list of them
     * Example:
     * <code>
     * //Add a resource to the the list allowing access to an action
     * $acl->addResource(new Phalcon\Acl\Resource('customers'), 'search');
     * $acl->addResource('customers', 'search');
     * //Add a resource  with an access list
     * $acl->addResource(new Phalcon\Acl\Resource('customers'), array('create', 'search'));
     * $acl->addResource('customers', array('create', 'search'));
     * </code>
     *
     * @param Acl\ResourceInterface $resource
     * @param null                  $accessList
     *
     * @return bool
     */
    public function addResource($resource, $accessList = null)
    {

        if (!is_object($resource)) {
            $resource = new Resource($resource);
        }

        $resources = $this->_getCollection('resources');

        $exists = $resources->count(array('name' => $resource->getName()));
        if (!$exists) {
            $resources->insert(array(
                'name'        => $resource->getName(),
                'description' => $resource->getDescription()
            ));
        }

        if ($accessList) {
            return $this->addResourceAccess($resource->getName(), $accessList);
        }

        return true;
    }

    /**
     * Adds access to resources
     *
     * @param string $resourceName
     * @param mixed  $accessList
     *
     * @return bool
     * @throws \Phalcon\Acl\Exception
     */
    public function addResourceAccess($resourceName, $accessList)
    {

        if (!$this->isResource($resourceName)) {
            throw new Exception("Resource '" . $resourceName . "' does not exist in ACL");
        }

        $resourcesAccesses = $this->_getCollection('resourcesAccesses');

        if (is_array($accessList)) {
            foreach ($accessList as $accessName) {
                $exists = $resourcesAccesses->count(array(
                    'resources_name' => $resourceName,
                    'access_name'    => $accessName
                ));
                if (!$exists) {
                    $resourcesAccesses->insert(array(
                        'resources_name' => $resourceName,
                        'access_name'    => $accessName
                    ));
                }
            }
        } else {
            $exists = $resourcesAccesses->count(array(
                'resources_name' => $resourceName,
                'access_name'    => $accessList
            ));
            if (!$exists) {
                $resourcesAccesses->insert(array(
                    'resources_name' => $resourceName,
                    'access_name'    => $accessList
                ));
            }
        }

        return true;
    }

    /**
     * Returns all resources in the access list
     *
     * @return \Phalcon\Acl\Resource[]
     */
    public function getResources()
    {
        $resources = array();
        foreach ($this->_getCollection('resources')->find() as $row) {
            $resources[] = new Resource($row['name'], $row['description']);
        }
        return $resources;
    }

    /**
     * Returns all resources in the access list
     *
     * @return \Phalcon\Acl\Role[]
     */
    public function getRoles()
    {
        $roles = array();
        foreach ($this->_getCollection('roles')->find() as $row) {
            $roles[] = new Role($row['name'], $row['description']);
        }
        return $roles;
    }

    /**
     * Removes an access from a resource
     *
     * @param string $resourceName
     * @param mixed  $accessList
     */
    public function dropResourceAccess($resourceName, $accessList)
    {

    }

    /**
     * @param string $roleName
     * @param string $resourceName
     * @param string $accessName
     * @param int $action
     *
     * @return bool
     * @throws \Phalcon\Acl\Exception
     */
    protected function _insertOrUpdateAccess($roleName, $resourceName, $accessName, $action)
    {

        /**
         * Check if the access is valid in the resource
         */
        $exists = $this->_getCollection('resourcesAccesses')->count(array(
            'resources_name' => $resourceName,
            'access_name'    => $accessName
        ));
        if (!$exists) {
            throw new Exception("Access '" . $accessName . "' does not exist in resource '" . $resourceName . "' in ACL");
        }

        $accessList = $this->_getCollection('accessList');

        $access = $accessList->findOne(array(
            'roles_name'     => $roleName,
            'resources_name' => $resourceName,
            'access_name'    => $accessName
        ));
        if (!$access) {
            $accessList->insert(array(
                'roles_name'     => $roleName,
                'resources_name' => $resourceName,
                'access_name'    => $accessName,
                'allowed'        => $action
            ));
        } else {
            $access['allowed'] = $action;
            $accessList->save($access);
        }

        /**
         * Update the access '*' in access_list
         */
        $exists = $accessList->count(array(
            'roles_name'     => $roleName,
            'resources_name' => $resourceName,
            'access_name'    => '*'
        ));
        if (!$exists) {
            $accessList->insert(array(
                'roles_name'     => $roleName,
                'resources_name' => $resourceName,
                'access_name'    => '*',
                'allowed'        => $this->_defaultAccess
            ));
        }

        return true;
    }

    /**
     * Inserts/Updates a permission in the access list
     *
     * @param string $roleName
     * @param string $resourceName
     * @param string $access
     * @param int $action
     *
     * @throws \Phalcon\Acl\Exception
     */
    protected function _allowOrDeny($roleName, $resourceName, $access, $action)
    {

        if (!$this->isRole($roleName)) {
            throw new Exception('Role "' . $roleName . '" does not exist in the list');
        }

        if (is_array($access)) {
            foreach ($access as $accessName) {
                $this->_insertOrUpdateAccess($roleName, $resourceName, $accessName, $action);
            }
        } else {
            $this->_insertOrUpdateAccess($roleName, $resourceName, $access, $action);
        }
    }

    /**
     * Allow access to a role on a resource
     * You can use '*' as wildcard
     * Ej:
     * <code>
     * //Allow access to guests to search on customers
     * $acl->allow('guests', 'customers', 'search');
     * //Allow access to guests to search or create on customers
     * $acl->allow('guests', 'customers', array('search', 'create'));
     * //Allow access to any role to browse on products
     * $acl->allow('*', 'products', 'browse');
     * //Allow access to any role to browse on any resource
     * $acl->allow('*', '*', 'browse');
     * </code>
     *
     * @param string $roleName
     * @param string $resourceName
     * @param mixed  $access
     */
    public function allow($roleName, $resourceName, $access)
    {
        $this->_allowOrDeny($roleName, $resourceName, $access, Acl::ALLOW);
    }

    /**
     * Deny access to a role on a resource
     * You can use '*' as wildcard
     * Ej:
     * <code>
     * //Deny access to guests to search on customers
     * $acl->deny('guests', 'customers', 'search');
     * //Deny access to guests to search or create on customers
     * $acl->deny('guests', 'customers', array('search', 'create'));
     * //Deny access to any role to browse on products
     * $acl->deny('*', 'products', 'browse');
     * //Deny access to any role to browse on any resource
     * $acl->deny('*', '*', 'browse');
     * </code>
     *
     * @param string $roleName
     * @param string $resourceName
     * @param mixed  $access
     * @return boolean
     */
    public function deny($roleName, $resourceName, $access)
    {
         $this->_allowOrDeny($roleName, $resourceName, $access, Acl::DENY);
    }

    /**
     * Check whether a role is allowed to access an action from a resource
     * <code>
     * //Does Andres have access to the customers resource to create?
     * $acl->isAllowed('Andres', 'Products', 'create');
     * //Do guests have access to any resource to edit?
     * $acl->isAllowed('guests', '*', 'edit');
     * </code>
     *
     * @param string $role
     * @param string $resource
     * @param string $access
     *
     * @return bool
     */
    public function isAllowed($role, $resource, $access)
    {

        $accessList = $this->_getCollection('accessList');


        $access = $accessList->findOne(array(
            'roles_name'     => $role,
            'resources_name' => $resource,
            'access_name'    => $access
        ));
        if (is_array($access)) {
            return (bool) $access['allowed'];
        }

        /**
         * Check if there is an common rule for that resource
         */
        $access = $accessList->findOne(array(
            'roles_name'     => $role,
            'resources_name' => $resource,
            'access_name'    => '*'
        ));
        if (is_array($access)) {
            return (bool) $access['allowed'];
        }

        /*$sql = 'SELECT roles_inherit FROM roles_inherits WHERE roles_name = ?';
        $inheritedRoles = $this->_options['db']->fetchAll($sql, \Phalcon\Db::FETCH_NUM, array($role));*/

        /**
         * Check inherited roles for a specific rule
         */
        /*foreach ($inheritedRoles as $row) {
            $sql = 'SELECT allowed FROM ' . $this->_options['accessList'] . " WHERE roles_name = ? AND resources_name = ? AND access_name = ?";
            $allowed = $this->_options['db']->fetchOne($sql, \Phalcon\Db::FETCH_NUM, array($row[0], $resource, $access));
            if (is_array($allowed)) {
                return (bool) $allowed[0];
            }
        }*/

        /**
         * Check inherited roles for a specific rule
         */
        /*foreach ($inheritedRoles as $row) {
            $sql = 'SELECT allowed FROM ' . $this->_options['accessList'] . " WHERE roles_name = ? AND resources_name = ? AND access_name = ?";
            $allowed = $this->_options['db']->fetchOne($sql, \Phalcon\Db::FETCH_NUM, array($row[0], $resource, '*'));
            if (is_array($allowed)) {
                return (bool) $allowed[0];
            }
        }*/

        /**
         * Check if there is a common rule for that access
         */
        /*$sql = 'SELECT allowed FROM ' . $this->_options['accessList'] . " WHERE roles_name = ? AND resources_name = ? AND access_name = ?";
        $allowed = $this->_options['db']->fetchOne($sql, \Phalcon\Db::FETCH_NUM, array($role, '*', $access));
        if (is_array($allowed)) {
            return (bool) $allowed[0];
        }*/

        /**
         * Return the default access action
         */
        return $this->_defaultAccess;
    }

}
