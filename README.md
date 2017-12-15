# MonsieurBiz AMQP

Use the power of RabbitMQ in your e-commerce running with Magento 2.

## Installation

With [composer](https://getcomposer.org/): `composer require monsieurbiz/amqp`.

If you want to use delayed messages then you'll have to install the [Delayed Message Plugin][https://github.com/rabbitmq/rabbitmq-delayed-message-exchange] on your RabbitMQ instance.

## Create an exchange

```
magento monsieurbiz:amqp:exchange:create consume-me
```

With `consume-me` as exchange name.

You can also use a delayed exchange:

```
magento monsieurbiz:amqp:exchange:create --delayed consume-me
```

You can create multiple exchange at once:
```
magento monsieurbiz:amqp:exchange:create first-exchange-name second-exchange-name
```

## Consume a queue

You have to create a consumer/worker.

Take a look at `Console/Command/SampleCommand.php`, it is a basic consumer.


## Send a message in an exchange

Considering that `$amqp` is an instance of `\MonsieurBiz\Amqp\Helper\Amqp`.

### Direct message

```php
$amqp->sendMessage(
    'consume-me',
    ['my message content']
);
```

### Delayed message

You need to install the [Delayed Message Plugin](https://github.com/rabbitmq/rabbitmq-delayed-message-exchange) on your RabbitMQ instance.

```php
$amqp->sendMessage(
    'consume-me',
    ['my message content'],
    [
        'application_headers' => new AMQPTable([
            'x-delay' => 5000 // 5 seconds of delay
        ]),
    ]
);
```

## RPC

Considering that `$rpc` is an instance of `\MonsieurBiz\Amqp\Helper\Rpc`.

### Direct request

To send a request to the broker and get a response right away:

```php
$response = $rpc->directRequest(
    'consume-me',
    ['my message content']
);

echo $response; // ["my message content"]
```

### Batch messages

Before sending a batch, you should be aware on how RPC works.

Don't forget to keep the correlation identifier of every request you make.

```php
$c1 = $rpc->request('consume-me', ['my first message']);
$c2 = $rpc->request('consume-me', ['my second message']);

$responses = $rpc->getResponses();

var_dump($responses[$c1]); // string(20) "["my first message"]"
var_dump($responses[$c2]); // string(21) "["my second message"]"
```

More you have consumers running faster you'll get the responses.

## LICENSE

(c) Monsieur Biz <opensource@monsieurbiz.com>

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
