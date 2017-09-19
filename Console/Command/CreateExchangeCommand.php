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

use MonsieurBiz\Amqp\Helper\Amqp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateExchangeCommand extends Command
{

    /**
     * @var Amqp
     */
    private $amqp;

    /**
     * CreateQueueCommand constructor.
     * @param Amqp $amqp
     * @param null|string $name
     */
    public function __construct(Amqp $amqp, $name = null)
    {
        parent::__construct($name);
        $this->amqp = $amqp;
    }

    /**
     * Configures the current command.
     */
    public function configure()
    {
        $this->setName('monsieurbiz:amqp:exchange:create');
        $this->setDescription('Create exchange in AMQP');
        $this->addArgument('name', InputArgument::IS_ARRAY, 'Name of the exchange');
    }

    /**
     * @param InputInterface $input An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     * @return null|int null or 0 if everything went fine, or an error code
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $names = $input->getArgument('name');

        foreach ($names as $name) {
            $this->amqp->createExchange($name);
            $output->writeln(sprintf('Exchange <comment>%s</comment> created.', $name));
        }

        return 0;
    }

}
