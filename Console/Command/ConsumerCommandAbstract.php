<?php
/*
 * This file is part of the MonsieurBiz/Amqp package.
 *
 * (c) Monsieur Biz <hello@monsieurbiz.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MonsieurBiz\Amqp\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use MonsieurBiz\Amqp\Helper\Amqp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ConsumerCommandAbstract extends Command
{

    /**
     * @var State
     */
    protected $appState;

    /**
     * @var Amqp
     */
    protected $_amqp;

    /**
     * @var ResourceConnection
     */
    protected $_resourceConnection;

    /**
     * @var string
     */
    protected $_exchangeId;

    /**
     * @var string
     */
    protected $_errorExchangeId;

    /**
     * Process limit
     */
    protected $_processLimit = -1;

    /**
     * CreateQueueCommand constructor.
     * @param State $state
     * @param ResourceConnection $resourceConnection
     * @param Amqp $amqp
     * @param null|string $name
     * @internal param ResourceConnection $resource
     */
    public function __construct(State $state, ResourceConnection $resourceConnection, Amqp $amqp, $name = null)
    {
        parent::__construct($name);
        $this->_amqp = $amqp;
        $this->_resourceConnection = $resourceConnection;

        // App State + Area Code
        $this->appState = $state;
    }

    /**
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return int|null null or 0 if everything went fine, or an error code
     *
     * @throws \Exception
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        /*
         * We need to know the queue
         */
        if (null === $this->_exchangeId) {
            throw new \Exception("Please fill the property \$_exchangeId in your Command.");
        }

        // Consume
        $connection = $this->_amqp->getConnection();
        $channel = $connection->channel();
        $queueName = $this->_exchangeId;
        $channel->queue_bind($queueName, $this->_exchangeId);

        // Let's consume!
        $callback = [$this, 'callback'];

        // Quality Of Service: don't fetch a message before acknowledge of the previous one
        $channel->basic_qos(null, 1, null);
        $tag = $channel->basic_consume($queueName, '', false, false, false, false, function (AMQPMessage $message) use ($input, $output, $callback) {
            $response = Amqp::REJECT;
            try {
                $response = call_user_func($callback, $input, $output, $message);
            } catch (\Exception $e) {
                if (null !== $this->_errorExchangeId) {
                    if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                        $output->writeln((string) $e);
                    }
                    $this->_amqp->sendMessage($this->_errorExchangeId, [
                        'class' => get_class($this),
                        'exception_serialized' => serialize($e),
                        'message' => json_decode($message->getBody(), true),
                    ]);
                }
            }
            if ($response === null || $response === true || (int) $response & Amqp::ACK === Amqp::ACK) {
                $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
            } else {
                $requeue = (bool) $response;
                $message->delivery_info['channel']->basic_reject($message->delivery_info['delivery_tag'], $requeue);
            }
        });

        // Shutdown?
        register_shutdown_function(
            function (Amqp $amqp, AMQPChannel $channel)
            {
                $channel->close();
                $amqp->closeConnection();
            },
            $this->_amqp,
            $channel
        );

        $output->writeln(sprintf("<comment>Listening on exchange</comment> <info>%s</info> <comment>with tag</comment> <info>%s</info>\n", $this->_exchangeId, $tag));

        // Loop as long as the channel has callbacks registered
        $processed = 0;
        while (count($channel->callbacks)) {
            $channel->wait();
            if (++$processed === $this->_processLimit) {
                break;
            }
        }

        return 0;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param AMQPMessage $message
     *
     * @return
     */
    abstract public function callback(InputInterface $input, OutputInterface $output, AMQPMessage $message);

    /**
     * Try a simple `SELECT 1;` and close the connection if an Exception is thrown.
     * <p>The reconnect will occur later before the next SQL request.</p>
     *
     * @param OutputInterface|null $output
     * @param string $connectionName
     */
    protected function _tryAndReconnect(OutputInterface $output = null, $connectionName = ResourceConnection::DEFAULT_CONNECTION)
    {
        try {
            $c = $this->_resourceConnection->getConnection($connectionName);
            $c->query('SELECT 1;')->execute();
        } catch (\Exception $e) {
            if (null !== $output && $output->getVerbosity() === OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln('== RECONNECT DATABASE ==');
            }
            $this->_resourceConnection->closeConnection($connectionName);
        }
    }

    /**
     * Init the Application's area (use AREA_* constants)
     *
     * @param string $areaCode
     */
    protected function _initArea($areaCode = Area::AREA_ADMINHTML)
    {
        $this->appState->setAreaCode($areaCode);
    }

}
