<?php
namespace PHPFacile\Chat\Db\Service;

use PHPFacile\Chat\Service\ChatServiceInterface;
use PHPFacile\Chat\Service\ChatChannelServiceInterface;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Sql;

class ChatService implements ChatServiceInterface
{
    /**
     * Database adapter
     *
     * @var Zend\Db\Adapter\AdapterInterface
     */
    protected $adapter;

    /**
     * Service used to manage (check access rights) chat channels
     *
     * @var PHPFacile\Chat\Service\ChatChannelServiceInterface
     */
    protected $chatChannelService;

    /**
     * Name of the table where the chat messages are stored
     *
     * @var string
     */
    protected $tableName = 'chat_messages';

    /**
     * Name of the table field where the id of the message is stored
     *
     * @var string
     */
    protected $fieldId = 'id';

    /**
     * Name of the table field where the text of the message is stored
     *
     * @var string
     */
    protected $fieldMsg = 'msg';

    /**
     * Name of the table field where the id of the user who posted the message is stored
     *
     * @var string
     */
    protected $fieldUserId = 'user_id';

    /**
     * Name of the table field where the id of the chat channel is stored
     *
     * @var string
     */
    protected $fieldChannelId = 'channel_id';

    /**
     * Name of the table field where the date/time (in UTC) of message is stored
     *
     * @var string
     */
    protected $fieldInsertionDateTimeUTC = 'insertion_datetime_utc';


    /**
     * Constructor of the ChatService
     *
     * @param Zend\Db\Adapter\AdapterInterface                   $adapter            Database adapter
     * @param PHPFacile\Chat\Service\ChatChannelServiceInterface $chatChannelService Channel service
     */
    public function __construct(AdapterInterface $adapter, ChatChannelServiceInterface $chatChannelService)
    {
        $this->adapter            = $adapter;
        $this->chatChannelService = $chatChannelService;
    }

    /**
     * Posts a message to a chat channel
     *
     * @param string $msg       Text of the message to be posted to the chat channel
     * @param string $channelId Id of the channel to post to
     * @param string $userId    Id of the user who post the message
     * @param array  $extraData Associative array of data to be stored/processed with the message
     *                          where keys are the database field names.
     *
     * @return void
     */
    public function addMessage($msg, $channelId, $userId, $extraData = [])
    {
        if (false === $this->chatChannelService->isUserAllowedToAccessChannelMessages($userId, $channelId, ChatChannelServiceInterface::RIGHT_CHANNEL_MSG_WRITE)) {
            throw new \Exception('Access denied');
        }

        $sql = new Sql($this->adapter);
        switch ($this->adapter->driver->getDatabasePlatformName()) {
            case 'Sqlite':
                $nowUTCDb = new Expression('datetime(\'now\')');
                break;
            case 'Mysql':
                $nowUTCDb = new Expression('UTC_TIMESTAMP()');
                break;
            default:
                throw new \Exception('Unsupported vendor ['.$this->adapter->driver->getDatabasePlatformName().']');
        }

        $data = [
            $this->fieldMsg                  => $msg,
            $this->fieldUserId               => $userId,
            $this->fieldChannelId            => $channelId,
            $this->fieldInsertionDateTimeUTC => $nowUTCDb,
        ];
        if (count($extraData) > 0) {
            $data += $extraData;
        }

        $query = $sql
            ->insert($this->tableName)
            ->values($data);
        $stmt  = $sql->prepareStatementForSqlObject($query);
        $stmt->execute();
    }

    /**
     * Returns all messages posted to a chat channel
     * (if the user is allowed to access to the chat channel)
     *
     * @param string $channelId Id of the channel
     * @param string $userId    Id of the user who want to get the messages
     *
     * @return array            Array of StdClass with attributes
     *                          ->id       Id of the message
     *                          ->user->id Id of the user who posted the message
     *                          ->text     Content of the message
     *                          ->insertionDateTimeUTC (string) Y-m-d H:i:s format
     */
    public function getMessages($channelId, $userId)
    {
        if (false === $this->chatChannelService->isUserAllowedToAccessChannelMessages($userId, $channelId, ChatChannelServiceInterface::RIGHT_CHANNEL_MSG_READ)) {
            throw new \Exception('Access denied');
        }

        $sql   = new Sql($this->adapter);
        $query = $sql
            ->select($this->tableName)
            ->columns([$this->fieldId, $this->fieldMsg, $this->fieldUserId, $this->fieldInsertionDateTimeUTC])
            ->where([$this->fieldChannelId => $channelId]);
        $stmt  = $sql->prepareStatementForSqlObject($query);
        $rows  = $stmt->execute();

        $msgs = [];
        foreach ($rows as $row) {
            $msg           = new \StdClass();
            $msg->id       = $row[$this->fieldId];
            $msg->user     = new \StdClass();
            $msg->user->id = $row[$this->fieldUserId];
            $msg->text     = $row[$this->fieldMsg];
            $msg->insertionDateTimeUTC = $row[$this->fieldInsertionDateTimeUTC];

            $msgs[] = $msg;
        }

        return $msgs;
    }

    /**
     * Returns the date/time in UTC of the last message posted in a given chat channel
     * by a given user
     *
     * @param string $channelId Id of the channel
     * @param string $userId    Id of the user who posted the messages
     *
     * @return string Y-m-d H:i:s format or null if there is no message
     */
    public function getLastUserMessageDateTimeUTC($channelId, $userId)
    {
        $sql   = new Sql($this->adapter);
        $query = $sql
            ->select($this->tableName)
            ->columns([$this->fieldInsertionDateTimeUTC])
            ->where([$this->fieldChannelId => $channelId, $this->fieldUserId => $userId])
            ->order($this->fieldInsertionDateTimeUTC.' DESC')
            ->limit(1);
        $stmt  = $sql->prepareStatementForSqlObject($query);
        $rows  = $stmt->execute();
        if (1 !== count($rows)) {
            return null;
        }

        $row = $rows->current();
        return $row[$this->fieldInsertionDateTimeUTC];
    }
}
