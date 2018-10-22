<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\DbUnit\TestCaseTrait;

use PHPFacile\Chat\Db\Service\ChatService;
use PHPFacile\Chat\Service\ChatChannelServiceInterface;

final class ChatServiceTest extends TestCase
{
    use TestCaseTrait {
        TestCaseTrait::setUp as parentSetUp;
    }

    protected $adapter;
    protected $dbName;
    protected $connection;
    protected $bookingService;
    protected $bookingExtraDataService;

    public function getDataSet()
    {
        return $this->createMySQLXMLDataSet(__DIR__.'/db-init.xml');
    }

    public function getConnection()
    {
        if (null === $this->dbName) {
            $this->dbName = '/tmp/chat_test_'.date('YmdHid').'.sqlite';
            copy(__DIR__.'/ref_database.sqlite', $this->dbName);
        }
        $pdo = new PDO('sqlite:'.$this->dbName);
        return $this->createDefaultDBConnection($pdo, $this->dbName);
    }

    protected function setUp()
    {
        //parent::setUp(); // Required so as to rebuild the database (thanks to getDataSet()) but doesn't work like this in case of use of Trait
        $this->parentSetUp(); // Replacement for parent::setUp() in case of use of Trait
        if (null === $this->dbName) {
            $this->dbName = '/tmp/chat_test_'.date('YmdHid').'.sqlite';
            copy(__DIR__.'/ref_database.sqlite', $this->dbName);
        }
        $config = [
            'driver' => 'Pdo_Sqlite',
            'database' => $this->dbName,
        ];
        $this->adapter = new Zend\Db\Adapter\Adapter($config);

        $chatChannelService = new FakeChatChannelService();
        $this->chatService = new ChatService($this->adapter, $chatChannelService);
    }

    /**
     * @testdox The library must be able to ...
     */
    public function testAddMessage()
    {
        $channelId = 'channel1';
        $userId = 1;
        $text = 'yaya';
        $this->assertEquals([], $this->chatService->getMessages($channelId, $userId));
        $this->chatService->addMessage($text, $channelId, $userId);
        $msgs = $this->chatService->getMessages($channelId, $userId);
        $this->assertEquals(1, count($msgs));
        $msg = $msgs[0];
        $this->assertEquals($msg->text, $text);
        $this->assertGreaterThanOrEqual($msg->insertionDateTimeUTC, date('Y-m-d H:i:s'));
    }

    /**
     * @testdox The library must be able to ...
     * @expectedException Exception
     * @expectedExceptionMessage Access denied
     */
    public function testAUserMustNotBeAbleToGetMessagesFromAChannelIfHeIsNotAutorized()
    {
        $channelId = 'channel1';
        $userId = 2;
        $this->chatService->getMessages($channelId, $userId);
    }

    /**
     * @testdox The library must be able to ...
     * @expectedException Exception
     * @expectedExceptionMessage Access denied
     */
    public function testAUserMustNotBeAbleToWriteAMessageInAChannelIfHeIsNotAutorized()
    {
        $channelId = 'channel1';
        $userId = 2;
        $this->chatService->addMessage('humm', $channelId, $userId);
    }
}

class FakeChatChannelService implements ChatChannelServiceInterface
{
    public function isUserAllowedToAccessChannelMessages($userId, $channelId, $right)
    {
        if ('channel'.$userId !== $channelId) return false;
        return true;
    }
}
