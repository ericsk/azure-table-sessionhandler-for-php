<?php

/**
 * LICENSE: Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * PHP version 5
 *
 * @category  WindowsAzure
 * @package   ericsk\WindowsAzure
 * @author    Eric ShangKuan <ericsk@outloot.com>
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @link      http://github.com/ericsk/azure-table-sessionhandler-for-php
 */

namespace ericsk\WindowsAzure;

require_once 'vendor/autoload.php';

use WindowsAzure\Common\ServiceBuilder;
use WindowsAzure\Common\ServiceException;
use WindowsAzure\Table\Models\Entity;
use WindowsAzure\Table\Models\EdmType;
use WindowsAzure\Table\Models\TableServiceOptions;

/**
 *
 */
class WindowsAzureTableSessionHandler implements SessionHandlerInterface {

  /**
   * Windows Azure Table Service REST proxy instance.
   *
   * @var TableService
   */
  protected $_tableRestProxy;

  /**
   * The name of the table which stores session data.
   *
   * @var string
   */
  protected $_sessionContainer;

  /**
   * The name of the partition for storing session data.
   *
   * @var string
   */
  protected $_sessionContainerPartition;


  /**
   * Session handler constructor.
   *
   * @param $storageAccountName The Windows Azure Table Services account name.
   * @param $storageAccountKey The Windows Azure Table Service account key.
   * @param $sessionContainer The name of the table for storing session data.
   * @param $sessionContainerParition The name of the partition for storing session data.
   */
  public function __construct($storageAccountName, $storageAccountKey, $sessionContainer = 'phpsessions', $sessionContainerPartition = 'sessions') {
    // create the conneciton string for creating the table service rest proxy intance.
    $connectionString = "DefaultEndpointsProtocol=https;AccountName=" .
                        $storageAccountName .
                        ";AccountKey=" . 
                        $storageAccountKey;

    // create the table service instance.
    $this->_tableRestProxy = ServiceBuilder::getInstance()->createTableService($connectionString);

    // set up the table and partition names.
    $this->_sessionContainer = $sessionContainer;
    $this->_sessionContainerPartition = $sessionContainerPartition;
	
	// register the session shutdown function.
	register_shutdown_function('session_write_close');
  }

  /**
   * Destructor.
   */
  public function __destruct() {
    session_write_close();
  }

  /**
   * Callback function for session handler. It's invoked while the session is being opened.
   *
   * @param $savePath The path to store the session.
   * @param $sessionName The name of the session.
   *
   * @return boolean If the open operation success.
   */
  public function open($savePath, $sessionName) {
    try {
      // get table to see if the table exists.
      $this->_tableRestProxy->getTable($this->_sessionContainer);
    } catch (ServiceException $e) {
      // cannot get the table, so create it
      $this->_tableRestProxy->createTable($this->_sessionContainer);
    }
    return TRUE;
  }

  /**
   * Callback function for session handler. It's invoked while the session is being closed.
   *
   * @return boolean If the close operation success.
   */
  public function close() {
    // do nothing
    return TRUE;
  }

  /**
   * Callback function for session handler. It's invoked while the session data is being read.
   *
   * @param $sessionId The session ID.
   *
   * @return string The session data. It will retrun empty string if the session doesn't exist.
   */
  public function read($sessionId) {
    try {
      // try to retrieve the session content first to see if it exists
      $result = $this->_tableRestProxy->getEntity($this->_sessionContainer, $this->_sessionContainerPartition, $sessionId);
      // get the entity instance
      $entity = $result->getEntity();
      // deflat the serialized data
      return unserialize(base64_decode($entity->getPropertyValue('data')));
    } catch (ServiceException $e) {
      // the entity doesn't exist, return empty string according to the spec:
      //   http://www.php.net/manual/en/sessionhandlerinterface.read.php
      return '';
    }
  }

  /**
   * Callback function for session handler. It's invoked while the session data is being written.
   *
   * @param $sessionId The session ID.
   * @param $sessionData The data to be written in session.
   *
   * @return boolean If the write operation success.
   */
  public function write($sessionId, $sessionData) {
    // serialize and encode the session data.
    $serializedData = base64_encode(serialize($sessionData));

    try {
      // try to retrive the stored session entity and update it.
      $result = $this->_tableRestProxy->getEntity($this->_sessionContainer, $this->_sessionContainerPartition, $sessionId);
      $entity = $result->getEntity();

      // update data and expiry time
      $entity->setPropertyValue('data', $serializedData);
      $entity->setPropertyValue('expires', time());

      // update entity
      $this->_tableRestProxy->updateEntity($this->_sessionContainer, $entity);
    } catch (ServiceException $e) {
      // otherwise, create a new session entity to store the data.
      $entity = new Entity();

      // set partition key and use session id as the row key.
      $entity->setPartitionKey($this->_sessionContainerPartition);
      $entity->setRowKey($sessionId);
      // set data and expiry time
      $entity->addProperty('data', EdmType::STRING, $serializedData);
      $entity->addProperty('expires', EdmType::INT32, time());

      // insert the entity
      $this->_tableRestProxy->insertEntity($this->_sessionContainer, $entity);
    }
    return TRUE;
  }

  /**
   * Callback function for session handler. It's invoked while the session is being destroyed.
   *
   * @param $sessionId The session ID.
   *
   * @return boolean If the destroy process success.
   */
  public function destroy($sessionId) {
    try {
      $this->_tableRestProxy->deleteEntity($this->_sessionContainer, $this->_sessionContainerParition, $sessionId);
      return TRUE;
    } catch (ServiceException $e) {
      return FALSE;
    }
  }

  /**
   * Callback function for session handler. It's invoked while the session garbage collection starts.
   *
   * @param $lifeTime Specify the expiry time for cleaning outdated sessions.
   *
   * @return boolean If the gc operation success.
   */
  public function gc($lifeTime) {
    // search the entities that need to be deleted.
    $filter = 'PartitionKey eq\'' . $this->_sessionContainerPartition . '\' and expires lt ' . (time() - $lifeTime);
    try {
      $result = $this->_tableRestProxy->queryEntities($this->_sessionContainer, $filter);
      $entities = $result->getEntities();
      foreach ($entities as $entitiy) {
        $this->_tableRestProxy->deleteEntity($this->_sessionContainer, $this->_sessionContainerParition, $entity->getRowKey());
      }
      return TRUE;
    } catch (ServiceException $e) {
      return FALSE;
    }
  }
}
