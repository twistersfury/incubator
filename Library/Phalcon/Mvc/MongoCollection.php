<?php
namespace Phalcon\Mvc;

use MongoDB\BSON\ObjectID;
use Phalcon\Db\Adapter\MongoDB\Collection as AdapterCollection;
use Phalcon\Di;
use Phalcon\Mvc\Collection\Document;
use Phalcon\Mvc\Collection\Exception;
use Phalcon\Mvc\Collection as PhalconCollection;

/**
 * test Validation
 * test Behaviours
 * test all events are being triggered.
 *
 * Tested Using Unit Tests:
 * save()
 * delete()
 * find()
 * count()
 * findById()
 * findFirst()
 * aggregate()
 *
 * Tests To Write
 * --------------
 * -- Validation Functionality
 *
 * -- Behaviours
 *
 * -- Events
 *
 *
 * functions not included here:
 * summatory($field, $conditions, $finalize) Collection::group() Doesn't exist.
 *
 */

/**
 * Class MongoCollection
 *
 * @package Phalcon\Mvc
 */
abstract class MongoCollection extends PhalconCollection implements \MongoDB\BSON\Unserializable
{

    // @codingStandardsIgnoreStart
    static protected $_disableEvents;
    // @codingStandardsIgnoreEnd

    /**
     * Sets a value for the _id property, creates a MongoId object if needed
     *
     * @param mixed id
     */
    public function setId($id)
    {

        $mongoId=null;

        if (is_object($id)) {
            $mongoId=$id;
        } else {
            if ($this->_modelsManager->isUsingImplicitObjectIds($this)) {
                $mongoId=new ObjectID($id);
            } else {
                $mongoId=$id;
            }
        }

        $this->_id=$mongoId;

    }

    /**
     * Creates/Updates a collection based on the values in the attributes
     */
    public function save()
    {
        $dependencyInjector=$this->_dependencyInjector;

        if (!is_object($dependencyInjector)) {
            throw new Exception(
                "A dependency injector container is required to obtain the services related to the ORM"
            );
        }

        $source=$this->getSource();

        if (empty($source)) {
            throw new Exception("Method getSource() returns empty string");
        }

        $connection=$this->getConnection();

        $collection=$connection->selectCollection($source);

        $exists=$this->_exists($collection);

        if ($exists===false) {
            $this->_operationMade=self::OP_CREATE;
        } else {
            $this->_operationMade=self::OP_UPDATE;
        }

        /**
         * The messages added to the validator are reset here
         */
        $this->_errorMessages=[];

        $disableEvents=self::$_disableEvents;

        /**
         * Execute the preSave hook
         */
        if ($this->_preSave($dependencyInjector, $disableEvents, $exists)===false) {
            return false;
        }

        $data=$this->toArray();

        $success=false;

        /**
         * We always use safe stores to get the success state
         * Save the document
         */
        switch ($this->_operationMade) {
            case self::OP_CREATE:
                $status=$collection->insertOne($data);
                break;

            case self::OP_UPDATE:
                $status=$collection->updateOne(['_id'=>$this->_id], ['$set' => $this->toArray()]);
                break;

            default:
                throw new \Phalcon\Mvc\Application\Exception('Invalid operation requested for MongoCollection->save()');
                break;
        }

        if ($status->isAcknowledged()) {
            $success=true;

            if ($exists===false) {
                $this->_id=$status->getInsertedId();
            }
        } else {
            $success=false;
        }

        /**
         * Call the postSave hooks
         */
        return $this->_postSave($disableEvents, $success, $exists);

    }

    public static function findById($id)
    {

        if (!is_object($id)) {
            $classname =get_called_class();
            $collection=new $classname();

            if ($collection->getCollectionManager()->isUsingImplicitObjectIds($collection)) {
                $mongoId=new ObjectID($id);
            } else {
                $mongoId=$id;
            }
        } else {
            $mongoId=$id;
        }

        return static::findFirst([["_id"=>$mongoId]]);
    }

    public static function findFirst(array $parameters = null)
    {

        $className=get_called_class();

        $collection=new $className();

        $connection=$collection->getConnection();

        return static::_getResultset($parameters, $collection, $connection, true);

    }

    /**
     * @param array               $params
     * @param CollectionInterface $collection
     * @param \MongoDb            $connection
     * @param bool                $unique
     *
     * @return array
     * @throws Exception
     */
    // @codingStandardsIgnoreStart
    protected static function _getResultset($params, \Phalcon\Mvc\CollectionInterface $collection, $connection, $unique)
    {
        // @codingStandardsIgnoreEnd

        /**
         * Check if "class" clause was defined
         */
        if (isset($params['class'])) {
            $classname=$params['class'];

            $base=new $classname();

            if (!$base instanceof CollectionInterface||$base instanceof Document) {
                throw new Exception(
                    "Object of class '".$classname."' must be an implementation of 
                    Phalcon\\Mvc\\CollectionInterface or an instance of Phalcon\\Mvc\\Collection\\Document"
                );
            }
        } else {
            $base=$collection;
        }

        $source=$collection->getSource();

        if (empty($source)) {
            throw new Exception("Method getSource() returns empty string");
        }

        /**
         * @var \Phalcon\Db\Adapter\MongoDB\Collection $mongoCollection
         */
        $mongoCollection=$connection->selectCollection($source);

        if (!is_object($mongoCollection)) {
            throw new Exception("Couldn't select mongo collection");
        }

        $conditions=[];

        if (isset($params[0])||isset($params['conditions'])) {
            $conditions=(isset($params[0]))?$params[0]:$params['conditions'];
        }

        /**
         * Convert the string to an array
         */
        if (!is_array($conditions)) {
            throw new Exception("Find parameters must be an array");
        }

        $options=[];

        /**
         * Check if a "limit" clause was defined
         */
        if (isset($params['limit'])) {
            $limit=$params['limit'];

            $options['limit']=(int)$limit;

            if ($unique) {
                $options['limit']=1;
            }
        }

        /**
         * Check if a "sort" clause was defined
         */
        if (isset($params['sort'])) {
            $sort=$params["sort"];

            $options['sort']=$sort;
        }

        /**
         * Check if a "skip" clause was defined
         */
        if (isset($params['skip'])) {
            $skip=$params["skip"];

            $options['skip']=(int)$skip;
        }

        if (isset($params['fields'])&&is_array($params['fields'])&&!empty($params['fields'])) {
            $options['projection']=[];

            foreach ($params['fields'] as $key => $show) {
                $options['projection'][$key]=$show;
            }
        }

        /**
         * Perform the find
         */
        $cursor=$mongoCollection->find($conditions, $options);

        $cursor->setTypeMap(['root'=>get_called_class(),'document'=>'object']);


        if ($unique===true) {
            /**
             * Loooking for only the first result.
             */
            return current($cursor->toArray());
        }

        /**
         * Requesting a complete resultset
         */
        $collections=[];


        foreach ($cursor as $document) {
            /**
             * Assign the values to the base object
             */
            $collections[]=$document;
        }

        return $collections;

    }

    /**
     * Deletes a model instance. Returning true on success or false otherwise.
     *
     * <code>
     *    $robot = Robots::findFirst();
     *    $robot->delete();
     *
     *    foreach (Robots::find() as $robot) {
     *        $robot->delete();
     *    }
     * </code>
     */
    public function delete()
    {

        if (!$id=$this->_id) {
            throw new Exception("The document cannot be deleted because it doesn't exist");
        }


        $disableEvents=self::$_disableEvents;

        if (!$disableEvents) {
            if ($this->fireEventCancel("beforeDelete")===false) {
                return false;
            }
        }

        if ($this->_skipped===true) {
            return true;
        }

        $connection=$this->getConnection();

        $source=$this->getSource();
        if (empty($source)) {
            throw new Exception("Method getSource() returns empty string");
        }

        /**
         * Get the Collection
         *
         * @var AdapterCollection $collection
         */
        $collection=$connection->selectCollection($source);

        if (is_object($id)) {
            $mongoId=$id;
        } else {
            if ($this->_modelsManager->isUsingImplicitObjectIds($this)) {
                $mongoId=new ObjectID($id);
            } else {
                $mongoId=$id;
            }
        }


        $success=false;

        /**
         * Remove the instance
         */
        $status=$collection->deleteOne(['_id'=>$mongoId], ['w'=>true]);

        if ($status->isAcknowledged()) {
            $success=true;

            $this->fireEvent("afterDelete");
        }

        return $success;

    }

    /**
     * Checks if the document exists in the collection
     *
     * @param \MongoCollection collection
     *
     * @return boolean
     */
    // @codingStandardsIgnoreStart
    protected function _exists($collection)
    {
        // @codingStandardsIgnoreStart

        if (!$id=$this->_id) {
            return false;
        }

        if (is_object($id)) {
            $mongoId=$id;
        } else {

            /**
             * Check if the model use implicit ids
             */
            if ($this->_modelsManager->isUsingImplicitObjectIds($this)) {
                $mongoId=new ObjectID($id);
            } else {
                $mongoId=$id;
            }
        }

        /**
         * Perform the count using the function provided by the driver
         */
        return $collection->count(["_id"=>$mongoId])>0;

    }

    /**
     * Fires an internal event that cancels the operation
     */
    public function fireEventCancel($eventName)
    {
        /**
         * Check if there is a method with the same name of the event
         */
        if (method_exists($this, $eventName)) {
            if ($this->{$eventName}()===false) {
                return false;
            }
        }

        /**
         * Send a notification to the events manager
         */
        if ($this->_modelsManager->notifyEvent($eventName, $this)===false) {
            return false;
        }

        return true;
    }

    public static function summatory($field, $conditions = null, $finalize = null){
        throw new \Phalcon\Mvc\Application\Exception(
            'The summatory() method is not implemented in the new Mvc MongoCollection'
        );
    }

    /**
     * Pass the values from the BSON document back to the object.
     *
     * @param array $data
     */
    public function bsonUnserialize(array $data)
    {

        $this->setDI(Di::getDefault());
        $this->_modelsManager=Di::getDefault()->getShared('collectionManager');

        foreach ($data as $key => $val) {
            $this->{$key}=$val;
        }

        if (method_exists($this, "afterFetch")) {
            $this->afterFetch();
        }

    }
}