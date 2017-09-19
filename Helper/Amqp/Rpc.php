<?php
/*
 * This file is part of the MonsieurBiz/Amqp package.
 *
 * (c) Monsieur Biz <hello@monsieurbiz.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
namespace MonsieurBiz\Amqp\Helper\Amqp;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use MonsieurBiz\Amqp\Helper\Amqp;

class Rpc
{

    /**
     * @var Amqp
     */
    private $amqp;

    /**
     * @var string
     */
    private $identifier;

    /**
     * @var array
     */
    private $requested = [];

    /**
     * @var AMQPChannel
     */
    private $channel;

    /**
     * Rpc constructor.
     * @param Amqp $amqp
     */
    public function __construct(Amqp $amqp)
    {
        $this->amqp = $amqp;
        $this->identifier = uniqid('rpc__');
    }

    protected function _declareQueue()
    {
        if (null === $this->channel) {
            // Declare queue
            $this->channel = $this->amqp->getChannel();
            $this->channel->queue_declare($this->identifier, false, false, true, false);
        }
    }

    /**
     * @param string $exchange
     * @param $message
     * @param array $properties
     * @return mixed
     */
    public function directRequest(string $exchange, $message, array $properties = [])
    {
        $oldRequested = $this->requested;
        $this->requested = [];

        $respId = $this->request($exchange, $message, $properties);
        $responses = $this->getResponses();

        $this->requested = $oldRequested;

        return $responses[$respId];
    }

    /**
     * @param string $exchange
     * @param $message
     * @param array $properties
     * @return string
     */
    public function request(string $exchange, $message, array $properties = [])
    {
        $this->_declareQueue();
        $responseId = uniqid('response__');
        $this->amqp->sendMessage($exchange, $message, $properties + [
            'correlation_id' => $responseId,
            'reply_to' => $this->identifier,
        ], Amqp::MODE_BATCH, $this->channel);

        $this->requested[] = $responseId;
        return $responseId;
    }

    /**
     * @return array
     */
    public function getResponses()
    {
        if (!count($this->requested)) {
            return [];
        }

        $this->_declareQueue();

        // Consumer
        $responses = [];
        $requested = $this->requested;
        $allResponded = false;
        $this->channel->basic_consume(
            $this->identifier, '', false, false, false, false,
            function (AMQPMessage $message) use (&$requested, &$responses, &$allResponded) {
                if (in_array($message->get('correlation_id'), $requested)) {
                    $responses[$message->get('correlation_id')] = $message->getBody();
                    // All responded?
                    if (!$allResponded) {
                        $allResponded = true;
                        foreach ($requested as $id) {
                            if (!isset($responses[$id])) {
                                $allResponded = false;
                            }
                        }
                    }
                    $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
                    return;
                }
                $message->delivery_info['channel']->basic_reject($message->delivery_info['delivery_tag'], true);
            }
        );

        // Send messages
        $this->amqp->sendBatch($this->channel);

        // Consume
        while (!$allResponded) {
            $this->channel->wait();
        }

        return $responses;
    }

}
