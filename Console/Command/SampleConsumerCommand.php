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

use PhpAmqpLib\Message\AMQPMessage;
use MonsieurBiz\Amqp\Helper\Amqp;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SampleConsumerCommand extends ConsumerCommandAbstract
{

    /**
     * @var string
     */
    protected $_exchangeId = 'consume-me';

    public function configure()
    {
        $this->setName('monsieurbiz:amqp:consumer:sample');
        $this->setDescription('Sample consumer. Consumes on queue name "consume-me". It displays the message\'s body.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param AMQPMessage $message
     */
    public function callback(InputInterface $input, OutputInterface $output, AMQPMessage $message)
    {
        // Try the database connection and reconnect if necessary
        $this->_tryAndReconnect($output);

        $output->writeln($message->getBody());

        if ($message->get('reply_to')) {
            $this->_amqp->sendMessage($message->get('reply_to'), json_decode($message->getBody(), true), [
                'correlation_id' => $message->get('correlation_id')
            ], Amqp::MODE_RPC_CALLBACK);
        }

    }

}
