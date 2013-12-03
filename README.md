# Windows Azure Table Session Handler

This library provides a PHP session handler that uses Windows Azure Table Service as the storage backend.

## Dependencies

- [Windows Azure SDK for PHP](https://github.com/WindowsAzure/azure-sdk-for-php)

## Usage
  
1. Install the necessary libraries using Composer. 

 - Download **[composer.phar](http://getcomposer.org/composer.phar)** and put in your project root with **composer.json**.
 
 - Open a command prompt and execute this in your project root

            php composer.phar install

   > **Note**
   >
   > On Windows, you will also need to add the Git executable to your PATH environment variable.

2. Put the **ericsk\WindowsAzure** in your project root too.

3. Add the following code before calling **session_start()**

        require 'ericsk/WindowsAzure/WindowsAzureTableSessionHandler.php';
        use ericsk\WindowsAzure\WindowsAzureTableSessionHandler;

        $sessionHandler = new WindowsAzureTableSessionHandler(
            'YOUR_TABLE_STORAGE_ACCOUNT_NAME',
            'YOUR_TABLE_STORAGE_ACCOUNT_KEY'
        );

        session_set_save_handler($session_handler, TRUE);

        session_start();

        // use $_SESSION as usual...
