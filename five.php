<?php
/**
 * 多进程IO复用模式Event
 */
class Server
{
    /**
     * 监听的socket
     * @var resource
     */
    private $socket;
    /**
     * 连接时回调函数
     * @var callable
     */
    public $onConnect = null;
    /**
     * 客户端发来消息时回调函数
     * @var callable
     */
    public $onMessage = null;
    /**
     * 关闭连接时回调函数
     * @var callable
     */
    public $onClose = null;
    /**
     * 保存连接
     * @var array
     */
    protected $conns = [];
    /**
     * Event base.
     * @var object
     */
    protected $eventBase = null;
    /**
     * 保存事件
     * @var array
     */
    protected $events = [];
    /**
     * 保存数据
     * @var array
     */
    protected $recvData = [];
    /**
     * 开启子进程数
     * @var int
     */
    public $count = 2;
    /**
     * 当数据可读时，从socket缓冲区读取多少字节数据
     */
    const READ_BUFFER_SIZE = 65535;
    /**
     * Server constructor.
     * @param string $host
     * @param string $port
     */
    public function __construct($host = "0.0.0.0", $port = "1993")
    {
        //监听socket
        $this->socket = stream_socket_server("tcp://{$host}:{$port}",$errno, $errstr);
        if (!$this->socket) {
            echo new Exception($errstr." -- ".$errno);
        }
        stream_set_blocking($this->socket,0);
    }
    /**
     * 启动服务
     */
    public function start()
    {
        for ($i = 0; $i < $this->count; $i++) {
            $this->forkOne();
        }
        while(1) {
            //挂起主进程
            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED);
            if ($pid > 0) {
                //如果有子进程退出,可以做一些事情。比如记录日志,重新生成一个子进程
            }
        }
    }
    /**
     * 开启子进程
     */
    protected function forkOne()
    {
        $pid = pcntl_fork();
        if ($pid === -1) {
            echo new Exception("fork fail");
        } elseif ($pid === 0) {
            $this->eventBase = new EventBase();
            $event = new Event($this->eventBase, $this->socket, Event::READ | Event::PERSIST, [$this, 'evAccept'], $this->socket);
            if (!$event || !$event->add()) {
                return false;
            }
            $this->eventBase->loop();
        }
    }
    /**
     * 接收请求
     * @param $socket
     * @return bool
     */
    public function evAccept($socket)
    {
        $new_socket = stream_socket_accept($socket, -1, $remote_address);
        if($this->onConnect) {
            call_user_func($this->onConnect, $new_socket);
        }
        $fdKey = (int)$new_socket;
        $event = new Event($this->eventBase, $new_socket, Event::READ | Event::PERSIST, [$this, 'evRecv'], $new_socket);
        if (!$event || !$event->add()) {
            return false;
        }
        $this->conns[$fdKey] = $new_socket;
        $this->events[$fdKey][Event::READ | Event::PERSIST] = $event;
    }
    /**
     * 接收消息
     * @param $socket
     */
    public function evRecv($socket)
    {
        $fdKey = (int)$socket;
        $buffer = fread($socket, self::READ_BUFFER_SIZE);
        if ($buffer === '' || $buffer === false) {
            if (feof($socket) || !is_resource($socket) || $buffer === false) {
                unset($this->conns[$fdKey]);
                unset($this->events[$fdKey][Event::READ | Event::PERSIST]);
                unset($this->recvData[$fdKey]);
                if ($this->onClose) {
                    call_user_func($this->onClose);
                }
            }
        } else {
            if (!isset($this->recvData[$fdKey])) {
                $this->recvData[$fdKey] = '';
            }
            $this->recvData[$fdKey] .= $buffer;
            //TODO 自定义协议
            if ($this->onMessage) {
                call_user_func($this->onMessage,$socket,$this->recvData[$fdKey]);
            }
            $this->recvData[$fdKey] = "";
        }
    }
}
$server = new Server();
//$server->onConnect = function($conn) {
//    echo "new conn\n";
//    fwrite($conn, "welcome!\n");
//};
$server->onMessage = function($conn, $data) {
    //fwrite($conn, "hello {$data}");
    fwrite($conn,"HTTP/1.1 200 OK\r\nConnection: keep-alive\r\nServer: Dawn\1.1.4\r\n\r\nhello");
};
//$server->onClose = function() {
//    echo "conn closed\n";
//};
$server->start();
