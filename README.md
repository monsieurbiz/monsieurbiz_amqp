# MonsieurBiz AMQP

## Installation

With [composer](https://getcomposer.org/): `composer require monsieurbiz/amqp`.

## Create an exchange

```
magento monsieurbiz:amqp:exchange:create consume-me
```

With `consume-me` as exchange name.

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
