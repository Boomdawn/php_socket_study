<?php
//有生产者和消费者两个角色。
//生产者生产n个产品,每生产一个就发送给消费者,消费完毕后再切换回生产者继续生产。生产一个消费一个如此循环。
/**
 * 消费者
 */
function consumer()
{
    //返回给生产者的消息
    $result = "";
    while (true) {
        $n = (yield $result);
        echo "[消费者] 正在消费第{$n}个产品\n";
        $result = "我成功消费了第{$n}个产品\n";
    }
}
/**
 * 生产者
 * @param $consumer
 */
function produce($consumer)
{
    $n = 0;
    while($n < 5) {
        $n = $n + 1;
        echo "[生产者] 生产了第{$n}个产品\n";
        //发送给消费者
        $result = $consumer->send($n);
        echo "[消费者] {$result}\n";
    }
}
$consumer = consumer();
produce($consumer);
