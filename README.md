PHPFacile! Chat-Db
==================

This is an implementation of the phpfacile/chat interface using a database as a storage.

Installation
-----
At the root of your project type
```
composer require phpfacile/chat-db
```
Or add "phpfacile/chat-db": "^1.0" to the "require" part of your composer.json file
```composer
"require": {
    "phpfacile/chat-db": "^1.0"
}
```

Your database must contain a table "chat_messages" with (at least) the following fields:
* id: integer auto increment
* msg: string
* user_id: integer or string
* channel_id: integer or string
* insertion_datetime_utc: datetime or string

REM: In the current version table and field names are not configurable

Example of table creation query with SQLite  (for test purpose only)
```sqlite
CREATE TABLE chat_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    channel_id INTEGER UNSIGNED NOT NULL,
    user_id INTEGER UNSIGNED NOT NULL,
    msg TEXT NOT NULL,
    insertion_datetime_utc DATETIME NOT NULL
);
```
Example of table creation query with MySQL
```mysql
CREATE TABLE `chat_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `msg` TEXT NOT NULL,
  `insertion_datetime_utc` DATETIME NOT NULL,
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

Usage
-----
### Step 1 : Adapter instanciation ###
Instanciate a Zend Adapter to allow a connexion to a database.

Example with SQLite (for test purpose only)
```php
$config = [
    'driver' => 'Pdo_Sqlite',
    'database' => 'my_chat_database.sqlite',
];
$adapter = new Zend\Db\Adapter\Adapter($config);
```

Example with MySQL
```php
$config = [
    'driver' => 'Pdo_Mysql',
    'host' => 'localhost'
    'dbname' => 'my_database',
    'user' => 'my_user',
    'password' => 'my_pass',
];
$adapter = new Zend\Db\Adapter\Adapter($config);
```

### Step 2 : ChatChannelService instanciation ###
```php
use PHPFacile\Chat\Service\ChatChannelService;

$chatChannelService = new ChatChannelService();
```
REM: You might have to overwrite the default ChatChannelService so as to control user accesses. (Cf. below)

### Step 3 : ChatService instanciation ###
```php
use PHPFacile\Chat\Service\ChatService;

$chatService = new ChatService($adapter, $chatChannelService);
```

### Step 4 : Post or get messages or messages information ###
#### addMessage ####
You can store add a new message to a chat channel by providing a text, a channel id and a user id to the __addMessage()__ method.
```php
$chatService->addMessage($text, $channelId, $userId);
```
REM: This method will not check whether the $channelId or the $userId (already) exists

#### getMessages ####
You can retrieve all the messages (and its metadata) from a chat channel by providing a channel id and a user id to the __getMessages()__ method.
```php
$msgs = $chatService->getMessages($channelId, $userId);
```
This will return an array of StdClass containing both the text and associated data of the messages.
```php
foreach ($msgs as $msg) {
    echo 'Id of the msg = '.$msg->id."\n";
    echo 'Text = '.$msg->text."\n";
    echo 'User id = '.$msg->user->id."\n";
    echo 'Posted at = '.$msg->insertionDateTimeUTC."\n";
}
```

#### getLastUserMessageDateTimeUTC ####
You can also retrieve the date/time (in UTC) of the last message posted by a user in a chat channel by providing a channel id and a user id to the __getLastUserMessageDateTimeUTC()__ method.
```php
$dateTime = $chatService->getLastUserMessageDateTimeUTC($channelId, $userId);
```
This will return a date in string format (Y-m-d H:i:s) like '2018-12-25 22:30:10' or null if no message was posted.

### Advanced feature ###
You're invited to overwrite the default ChatChannelService (or have you own ChatChannelServiceInterface implementation) so as to write your own user access rights management.

```php
use PHPFacile\Chat\Service\ChatChannelServiceInterface;

class CustomChatChannelService implements ChatChannelServiceInterface
{
    public function isUserAllowedToAccessChannelMessages($userId, $channelId, $right)
    {
        // Return true if the user must be allowed to access
        // to the content of the chat channel either for
        // reading ($right = self::RIGHT_CHANNEL_MSG_READ) or for
        // writing ($right = self::RIGHT_CHANNEL_MSG_WRITE)
        return true;
    }
}

$chatChannelService = new CustomChatChannelService();
```

If ever you want you can store additionnal data with the posted message by providing an associative array as a 4th parameter of addMessage(). The array keys must match (existing) table field names.

```php
$extraData = [
    'software' => 'MyChatApp',
];
$chatService->addMessage($text, $channelId, $userId, extraData);
```
