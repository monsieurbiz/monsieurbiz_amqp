<?php
/*
 * This file is part of the MonsieurBiz/Amqp package.
 *
 * (c) Monsieur Biz <hello@monsieurbiz.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
namespace MonsieurBiz\Amqp\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Amqp extends AbstractHelper
{

    const MODE_DIRECT = 1;
    const MODE_BATCH = 2;
    const MODE_RPC_CALLBACK = 4;

    const REJECT = 0;
    const ACK = 1;
    const REQUEUE = 2;

    /**
     * @var AMQPStreamConnection
     */
    private $connection;

    /**
     * @var AMQPChannel
     */
    protected $_channel;

    /**
     * @param string $name Exchange's name
     * @param bool $delayed
     */
    public function createExchange(string $name, bool $delayed = false)
    {
        $connection = $this->getConnection();
        $channel = $connection->channel();
        if ($delayed) {
            $channel->exchange_declare(
                $name, 'x-delayed-message', false, true, false, false, false, [
                'x-delayed-type' => ['S', 'direct'],
            ]);
        } else {
            $channel->exchange_declare($name, 'direct', false, true, false, false, false, []);
        }
        $channel->queue_declare($queueName = $name, false, true, false, false);
        $channel->queue_bind($queueName, $name);
    }

    /**
     * @return AMQPStreamConnection
     */
    public function getConnection()
    {
        if (null === $this->connection) {
            $this->connection = new AMQPStreamConnection(
                $this->getHost(),
                $this->getPort(),
                $this->getUser(),
                $this->getPass(),
                $this->getVhost()
            );
        }
        return $this->connection;
    }

    public function closeConnection()
    {
        if ($this->connection) {
            $this->connection->close();
            $this->connection = null;
        }
    }

    /**
     * Retrieve the current channel
     * @return AMQPChannel
     */
    public function getChannel()
    {
        if (null === $this->_channel) {
            $this->_channel = $this->getConnection()->channel();
            $this->_channel->basic_qos(null, 1, null);
        }
        return $this->_channel;
    }

    /**
     * Send Message
     *
     * @param string $exchange
     * @param array|AMQPMessage $message
     * @param array $properties
     * @param int $mode
     * @param AMQPChannel|null $channel
     *
     * @return $this
     * @throws \Exception
     * @internal param string $route
     */
    public function sendMessage(string $exchange, $message, array $properties = [], $mode = self::MODE_DIRECT, AMQPChannel $channel = null)
    {
        if ($message instanceof AMQPMessage) {
            if (!empty($properties)) {
                throw new \Exception("Please do not fill the properties parameter if the message is an instance of AMQPMessage.");
            }
            $msg = $message;
        } else {
            $msg = new AMQPMessage(json_encode($message), $properties);
        }

        // Which channel?
        if (null === $channel) {
            $channel = $this->getChannel();
        }

        switch ($mode) {
            case self::MODE_DIRECT:
                $channel->basic_publish($msg, $exchange);
                break;
            case self::MODE_RPC_CALLBACK:
                $channel->basic_publish($msg, '', $exchange);
                break;
            case self::MODE_BATCH:
                $channel->batch_basic_publish($msg, $exchange);
                break;
        }

        return $this;
    }

    /**
     * Send messages in the current batch
     *
     * @param AMQPChannel|null $channel
     */
    public function sendBatch(AMQPChannel $channel = null)
    {
        // Which channel?
        if (null === $channel) {
            $channel = $this->getChannel();
        }

        $channel->publish_batch();
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return (string) $this->scopeConfig->getValue('monsieurbiz_amqp/broker/host');
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return (int) $this->scopeConfig->getValue('monsieurbiz_amqp/broker/port');
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return (string) $this->scopeConfig->getValue('monsieurbiz_amqp/broker/user');
    }

    /**
     * @return string
     */
    public function getPass()
    {
        return (string) $this->scopeConfig->getValue('monsieurbiz_amqp/broker/pass');
    }

    /**
     * @return string
     */
    public function getVhost()
    {
        return (string) $this->scopeConfig->getValue('monsieurbiz_amqp/broker/vhost');
    }

}
